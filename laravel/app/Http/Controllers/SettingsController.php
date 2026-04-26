<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\MailSetting;
use App\Models\SsoSetting;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
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

    public function getDefaultPrompt(): JsonResponse
    {
        $settings = AiSetting::getSettings();
        return response()->json(['prompt' => $settings->default_reply_prompt ?? '']);
    }

    public function saveDefaultPrompt(Request $request): JsonResponse
    {
        $settings = AiSetting::getSettings();
        $settings->update(['default_reply_prompt' => $request->input('prompt', '')]);
        return response()->json(['status' => 'ok']);
    }

    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'anthropic_api_key'    => 'nullable|string|max:2048',
            'gemini_api_key'       => 'nullable|string|max:2048',
            'default_provider'     => 'required|in:ollama,claude,gemini',
            'default_model'        => 'nullable|string|max:128',
            'default_reply_prompt' => 'nullable|string|max:5000',
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

    public function sso()
    {
        $settings = SsoSetting::getSettings();
        return view('settings.sso', compact('settings'));
    }

    public function updateSso(Request $request)
    {
        $validated = $request->validate([
            'is_enabled'           => 'nullable|boolean',
            'google_client_id'     => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',
            'google_redirect_uri'  => 'nullable|url|max:500',
            'require_invitation'   => 'nullable|boolean',
        ]);

        $settings = SsoSetting::getSettings();
        
        $settings->update([
            'is_enabled'           => $request->has('is_enabled'),
            'google_client_id'     => $validated['google_client_id'],
            'google_client_secret' => $validated['google_client_secret'],
            'google_redirect_uri'  => $validated['google_redirect_uri'],
            'require_invitation'   => $request->has('require_invitation'),
        ]);

        return redirect()->route('settings.sso')->with('success', 'SSO設定を保存しました');
    }
}
