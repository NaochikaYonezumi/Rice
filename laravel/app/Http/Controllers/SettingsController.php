<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\MailSetting;
use App\Services\RagApiService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function mail()
    {
        $settings = MailSetting::getSettings();
        return view('settings.mail', compact('settings'));
    }

    public function updateMail(Request $request)
    {
        $validated = $request->validate([
            'smtp_host'         => 'nullable|string|max:255',
            'smtp_port'         => 'required|integer',
            'smtp_encryption'   => 'required|in:tls,ssl,null',
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:255',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name'    => 'nullable|string|max:255',
            'inbox_protocol'    => 'required|in:imap,pop3',
            'imap_host'         => 'nullable|string|max:255',
            'imap_port'         => 'required|integer',
            'imap_encryption'   => 'required|in:ssl,tls,null',
            'imap_username'     => 'nullable|string|max:255',
            'imap_password'     => 'nullable|string|max:255',
            'imap_folder'       => 'nullable|string|max:255',
            'pop_host'          => 'nullable|string|max:255',
            'pop_port'          => 'required|integer',
            'pop_encryption'    => 'required|in:ssl,tls,null',
            'pop_username'      => 'nullable|string|max:255',
            'pop_password'      => 'nullable|string|max:255',
        ]);

        $settings = MailSetting::getSettings();
        $settings->update($validated);

        return redirect()->route('settings.mail')->with('success', '設定を保存しました');
    }

    public function ai(RagApiService $ragApi)
    {
        $settings = AiSetting::getSettings();
        $models = [];
        try {
            $models = $ragApi->getModels();
        } catch (\Throwable) {}

        return view('settings.ai', compact('settings', 'models'));
    }

    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'anthropic_api_key' => 'nullable|string|max:2048',
            'gemini_api_key'    => 'nullable|string|max:2048',
            'default_provider'  => 'required|in:ollama,claude,gemini',
            'default_model'     => 'nullable|string|max:128',
        ]);

        $settings = AiSetting::getSettings();

        if (empty($validated['anthropic_api_key'])) {
            unset($validated['anthropic_api_key']);
        }
        if (empty($validated['gemini_api_key'])) {
            unset($validated['gemini_api_key']);
        }

        try {
            $settings->update($validated);
        } catch (\Throwable $e) {
            return redirect()->route('settings.ai')
                ->withInput()
                ->with('error', 'APIキーの保存に失敗しました: ' . $e->getMessage());
        }

        return redirect()->route('settings.ai')->with('success', '設定を保存しました');
    }
}
