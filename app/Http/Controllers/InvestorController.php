<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Profile;
use App\Models\Message;
use App\Models\Investment; // Add this line
use Illuminate\Support\Facades\Storage;

class InvestorController extends Controller
{
    public function home()
    {
        $user = Auth::user();
        $firstname = $user->first_name ?? 'Investor';
        return view('investor.home', compact('firstname'));
    }

    public function portfolio()
    {
        $investor = auth()->user();
        
        // Get all investments for the investor with their related projects
        $investments = Investment::with('project')
            ->where('investor_id', $investor->user_id)
            ->orderBy('investment_date', 'desc')
            ->get();
    
        // Calculate portfolio statistics
        $totalInvested = $investments->sum('investment_amount');
        $activeInvestments = $investments->where('investment_status', 'Confirmed')->count();
        
        // Calculate simplified ROI and returns (you may want to implement your own logic)
        $averageROI = 0;
        $totalReturns = 0;
        
        if ($activeInvestments > 0) {
            $averageROI = 8.5; // Example fixed ROI
            $totalReturns = $totalInvested * ($averageROI / 100);
        }
    
        return view('investor.portfolio', compact(
            'investments',
            'totalInvested',
            'activeInvestments',
            'averageROI',
            'totalReturns'
        ));
    }

    public function financial()
    {
        $user_id = auth()->id();
        
        // Fetch basic stats
        $stats = [
            'current_balance' => $this->calculateBalance($user_id),
            'total_deposits' => Transaction::where('user_id', $user_id)
                ->where('transaction_type', 'Deposit')
                ->where('transaction_status', 'Success')
                ->sum('amount'),
            'total_investments' => Transaction::where('user_id', $user_id)
                ->where('transaction_type', 'Investment')
                ->where('transaction_status', 'Success')
                ->sum('amount'),
            'pending_transactions' => Transaction::where('user_id', $user_id)
                ->where('transaction_status', 'Pending')
                ->count()
        ];

        // Prepare chart data
        $chartData = [
            'months' => $this->getLast6Months(),
            'investments' => $this->getMonthlyData($user_id, 'Investment'),
            'deposits' => $this->getMonthlyData($user_id, 'Deposit'),
            'monthlyInvestments' => $this->getMonthlyData($user_id, 'Investment'),
            'transactionTypes' => ['Deposit', 'Investment', 'Milestone Payment', 'Refund'],
            'transactionAmounts' => $this->getTransactionTypeDistribution($user_id)
        ];

        $transactions = Transaction::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('investor.financial', compact('stats', 'chartData', 'transactions'));
    }

    private function getLast6Months()
    {
        return collect(range(5, 0))->map(function($i) {
            return now()->subMonths($i)->format('M Y');
        })->toArray();
    }

    private function getMonthlyData($user_id, $type)
    {
        return collect(range(5, 0))->map(function($i) use ($user_id, $type) {
            $month = now()->subMonths($i);
            return Transaction::where('user_id', $user_id)
                ->where('transaction_type', $type)
                ->where('transaction_status', 'Success')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('amount');
        })->toArray();
    }

    private function getTransactionTypeDistribution($user_id)
    {
        return collect(['Deposit', 'Investment', 'Milestone Payment', 'Refund'])
            ->map(function($type) use ($user_id) {
                return Transaction::where('user_id', $user_id)
                    ->where('transaction_type', $type)
                    ->where('transaction_status', 'Success')
                    ->sum('amount');
            })->toArray();
    }

    private function calculateBalance($user_id)
    {
        $deposits = Transaction::where('user_id', $user_id)
            ->where('transaction_type', 'Deposit')
            ->where('transaction_status', 'Success')
            ->sum('amount');
            
        $investments = Transaction::where('user_id', $user_id)
            ->whereIn('transaction_type', ['Investment', 'Milestone Payment'])
            ->where('transaction_status', 'Success')
            ->sum('amount');
            
        $refunds = Transaction::where('user_id', $user_id)
            ->where('transaction_type', 'Refund')
            ->where('transaction_status', 'Success')
            ->sum('amount');
            
        return $deposits - $investments + $refunds;
    }

    public function profile()
    {
        $user = Auth::user();
        $profile = $user->profile;

        // Get investment statistics
        $investments_count = Investment::where('investor_id', $user->user_id)->count();
        $total_invested = Investment::where('investor_id', $user->user_id)->sum('investment_amount');
        $roi = 8.5; // Example ROI calculation - implement your own logic

        // Get unread messages count
        $unreadMessages = Message::where('receiver_id', $user->user_id)
            ->where('is_read', false)
            ->count();

        return view('investor.profile', compact(
            'user',
            'profile',
            'investments_count',
            'total_invested',
            'roi',
            'unreadMessages'
        ));
    }

    public function updateProfile(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'bio' => 'required|string',
                'interests' => 'required|string',
                'profile_pic' => 'nullable|image|max:5120', // 5MB max
                'profile_pic_url' => 'nullable|url'
            ]);

            $user = Auth::user();
            $profile = Profile::where('user_id', $user->user_id)->first();

            if (!$profile) {
                $profile = new Profile(['user_id' => $user->user_id]);
            }

            $profile->name = $request->name;
            $profile->location = $request->location;
            $profile->bio = $request->bio;
            $profile->interests = array_map('trim', explode(',', $request->interests));

            // Handle profile picture upload
            if ($request->hasFile('profile_pic')) {
                if ($profile->profile_pic) {
                    Storage::disk('public')->delete('profile_pictures/' . $profile->profile_pic);
                }
                $fileName = time() . '_' . $request->file('profile_pic')->getClientOriginalName();
                $request->file('profile_pic')->storeAs('profile_pictures', $fileName, 'public');
                $profile->profile_pic = $fileName;
                $profile->profile_pic_url = null;
            } elseif ($request->profile_pic_url) {
                $profile->profile_pic_url = $request->profile_pic_url;
                $profile->profile_pic = null;
            }

            $profile->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function projects()
    {
        $projects = Project::with('user')->get();
        $totalProjects = $projects->count();
        $totalFundingNeeded = $projects->sum('funding_goal');

        return view('investor.projects', compact('projects', 'totalProjects', 'totalFundingNeeded'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login');
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = auth()->user();
        $amount = $request->input('amount');

        // Create a new transaction
        Transaction::create([
            'investment_id' => null, // or appropriate investment_id
            'user_id' => $user->user_id, // Ensure this is set correctly
            'amount' => $amount,
            'transaction_type' => 'Deposit',
            'transaction_status' => 'Success',
            'payment_gateway' => 'YourPaymentGateway', // or appropriate value
        ]);

        // Update user's balance
        $user->balance += $amount;
        $user->save();

        return redirect()->route('investor.financial')->with('success', 'Deposit successful.');
    }

    public function dashboard()
    {
        $user = auth()->user();
        
        // Get unread messages count
        $unreadMessages = Message::where('receiver_id', $user->user_id)
            ->where('is_read', false)
            ->count();

        return view('investor.dashboard', compact(
            // ... existing variables ...
            'unreadMessages'
        ));
    }

    public function chat()
    {
        $messages = collect(); // Initialize empty collection for messages
        $currentChat = null;  // Initialize currentChat as null
        
        $user = auth()->user();
        $conversations = Message::where('sender_id', $user->user_id)
            ->orWhere('receiver_id', $user->user_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(function($message) use ($user) {
                return $message->sender_id == $user->user_id ? $message->receiver_id : $message->sender_id;
            });

        $unreadMessages = Message::where('receiver_id', $user->user_id)
            ->where('is_read', false)
            ->count();

        return view('investor.chat', compact('conversations', 'unreadMessages', 'messages', 'currentChat'));
    }

    public function invest(Request $request, Project $project)
    {
        try {
            $request->validate([
                'amount' => [
                    'required',
                    'numeric',
                    'min:' . $project->minimum_investment,
                    'max:' . ($project->funding_goal - $project->current_funding)
                ]
            ]);

            $user = auth()->user();
            $amount = $request->amount;

            if ($user->balance < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance'
                ], 400);
            }

            DB::transaction(function() use ($user, $project, $amount) {
                // Create investment record
                Investment::create([
                    'investor_id' => $user->user_id,
                    'project_id' => $project->id,
                    'investment_amount' => $amount,
                    'investment_status' => 'Confirmed',
                    'investment_date' => now()
                ]);

                // Update project funding
                $project->current_funding += $amount;
                $project->save();

                // Update user balance
                $user->balance -= $amount;
                $user->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Investment successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
