<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\ListModel;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        // find the users lists and include items and store relations
        $lists = ListModel::with(['items.store', 'items.lists'])
            ->where('user_id', '=', Auth::id())
            ->get();

        return response()->json([
            "status" => "OK",
            "data" => $lists
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'is_public' => 'required|integer',
            'user_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "errors" => $validator->errors()
            ], 422);
        }

        // save a new list
        $list = new ListModel();
        $list->name = $request->input('name');
        $list->is_public = $request->input('is_public');
        $list->user_id = $request->input('user_id');
        $list->save();

        return response()->json([
            "status" => "OK",
            "data" => $list
        ]);
    }

    public function show($id)
    {
        $list = ListModel::find($id);

        if ($list !== null) {
            $list->load('items');
            $status = "OK";
            $code = 200;
        } else {
            $status = "Not found";
            $code = 404;
        }

        return response()->json([
            "status" => $status,
            "data" => $list
        ], $code);
    }

    public function update(Request $request, $id)
    {
        $list = ListModel::find($id);

        // couldn't find list
        if ($list === null) {
            return response()->json(
                [
                    "status" => "List not found",
                    "data" => null
                ], 404
            );
        }

        // validate the name, and is_public data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'is_public' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    "status" => "error",
                    "errors" => $validator->errors()
                ], 422
            );
        }

        // update the list
        $list->name = $request->input('name');
        $list->is_public = 0;
        $list->save();

        // load the items and store relations
        $list->load('items.store');

        return response()->json(
            [
                "status" => "OK",
                "data" => $list
            ], 200
        );
    }

    public function destroy($id)
    {
        $list = ListModel::find($id);

        if ($list === null) {
            $status = "List not found";
            $code = 404;
        } else {
            // retrieve the unlisted list
            $unlisted = Auth::user()
                ->lists()
                ->where('name', 'unlisted')
                ->first();

            // retrieve the list items
            $listItems = $list->items()->get();

            if ($listItems) {
                // loop through list items
                foreach ($listItems as $item) {
                    // remove item from existing list
                    $list->items()->detach($item->id);
                    // attach items to the unlisted list
                    $unlisted->items()->attach($item->id);
                }
            }

            // finally delete the list
            $list->delete();
            $status = "OK";
            $code = 200;
        }

        return response()->json(
            [
                "status" => $status,
                "data" => null
            ], $code
        );
    }
}
