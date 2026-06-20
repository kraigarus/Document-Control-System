<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function index()
    {
        return view('pages.dcs.create-update.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'version_id'   => 'required',
            'doc_type_id'  => 'required',
            'sub_type_id'  => 'required',
        ]);

        // Process based on version type and document type
        // Save to appropriate tables

        return redirect()
            ->route('register')
            ->with('success', 'Document registered successfully.');
    }

    public function revised()
    {
        return view('pages.dcs.create-update.register', ['type' => 'revised']);
    }

    public function update()
    {
        return view('pages.dcs.create-update.register', ['type' => 'update']);
    }
}