<?php

namespace App\Http\Controllers;

use App\Models\FiscalEntry;
use App\Models\User;
use Illuminate\Http\Request;

class FiscalEntryController extends Controller
{
    /**
     * Get the test user ID
     */
    private function getTestUserId()
    {
        // Get the test user or create if not exists
        return User::where('email', 'test@example.com')->first()->id ?? 
              User::create([
                  'name' => 'Test User',
                  'email' => 'test@example.com',
                  'password' => bcrypt('password'),
              ])->id;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = $this->getTestUserId();
        
        $entries = FiscalEntry::where('user_id', $userId)
            ->with('documents')
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('fiscal_period', 'desc')
            ->paginate(10);
            
        return response()->json($entries);
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'fiscal_year' => 'required|string',
            'fiscal_period' => 'required|string',
            'document_type' => 'required|string',
            'creator' => 'required|string',
        ]);
        
        $userId = $this->getTestUserId();
        
        $entry = new FiscalEntry();
        $entry->user_id = $userId; // Always use the test user ID
        $entry->fiscal_year = $request->fiscal_year;
        $entry->fiscal_period = $request->fiscal_period;
        $entry->document_type = $request->document_type;
        $entry->creator = $request->creator;
        $entry->last_modifier = $request->creator;
        $entry->status = 'active';
        $entry->save();
        
        return response()->json([
            'message' => 'Fiscal entry created successfully',
            'entry' => $entry
        ], 201);
    }
    
    /**
     * Display the specified resource.
     */
    public function show(FiscalEntry $entry)
    {
        // For testing purposes, we don't need to check if it belongs to the authenticated user
        
        return response()->json($entry->load('documents'));
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FiscalEntry $entry)
    {
        // For testing purposes, we don't need to check if it belongs to the authenticated user
        
        $request->validate([
            'fiscal_year' => 'sometimes|string',
            'fiscal_period' => 'sometimes|string',
            'document_type' => 'sometimes|string',
            'status' => 'sometimes|string|in:active,deleted',
        ]);
        
        // Update the entry with the request data
        $entry->fill($request->only([
            'fiscal_year',
            'fiscal_period',
            'document_type',
            'status',
        ]));
        
        // Update last modifier
        $entry->last_modifier = $request->last_modifier ?? 'Test User';
        $entry->save();
        
        return response()->json([
            'message' => 'Fiscal entry updated successfully',
            'entry' => $entry
        ]);
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FiscalEntry $entry)
    {
        // For testing purposes, we don't need to check if it belongs to the authenticated user
        
        // Soft delete by updating status
        $entry->status = 'deleted';
        $entry->save();
        
        return response()->json([
            'message' => 'Fiscal entry deleted successfully'
        ]);
    }
}