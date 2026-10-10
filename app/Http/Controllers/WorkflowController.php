<?php

namespace App\Http\Controllers;

class WorkflowController extends Controller
{
    /** The "how tickets move" guide for the JMS team. */
    public function index()
    {
        return view('workflow.index');
    }

    /** The same guide as a printable handout. */
    public function pdf()
    {
        return response()->download(resource_path('docs/JMS-Ticketing-Workflow.pdf'), 'JMS-Ticketing-Workflow.pdf');
    }
}
