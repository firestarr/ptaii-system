<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Return all users, optionally add pagination or filters if needed
        $users = User::select('id', 'name', 'email')->get();
        return response()->json(['data' => $users]);
    }

    public function current(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            return response()->json(['data' => $user]);
        } else {
            return response()->json(['data' => null], 401);
        }
    }
}
