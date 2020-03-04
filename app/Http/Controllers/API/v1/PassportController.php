<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\ListModel;
use App\Role;
use Illuminate\Http\Request;
use Validator;
use App\User;

class PassportController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        // attach the user role to newly created user
        $user->roles()->attach(Role::where('name', 'user')->first());

        $unlisted = new ListModel();
        $unlisted->name = "unlisted";
        $unlisted->is_public = 0;
        $unlisted->user_id = $user->id;
        $unlisted->save();

        $firstList = new ListModel();
        $firstList->name = "Your First List";
        $firstList->is_public = 0;
        $firstList->user_id = $user->id;
        $firstList->save();

        $token = $user->createToken('lyst-api')->accessToken;
        return response()->json(['token' => $token], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password
        ];
        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $token = $user->createToken('lyst-api')->accessToken;
            return response()->json([
                'name' => $user->name,
                'email' => $user->email,
                'token' => $token
            ], 200);
        } else {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
    }

    public function user()
    {
        return response()->json(['user' => auth()->user()], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Successfully logged out'], 200);
    }
}
