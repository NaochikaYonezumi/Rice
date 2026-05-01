<?php

namespace Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Knowledge\Services\NeuronService;

class KnowledgeController extends Controller
{
    public function index()
    {
        return view('knowledge::index');
    }

    public function crawl(Request $request, NeuronService $neuron)
    {
        $request->validate(['url' => 'required|url']);
        
        $result = $neuron->startCrawl($request->url);
        
        return back()->with('success', 'クローリングを開始しました。');
    }
}
