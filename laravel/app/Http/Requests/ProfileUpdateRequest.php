<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            // Phase 6-4: Agent 別メール署名
            'signature_text'    => ['nullable', 'string', 'max:2000'],
            'signature_html'    => ['nullable', 'string', 'max:10000'],
            'signature_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * バリデーション後の処理:
     * - signature_html は XSS 防止のためサニタイズしてから保存
     * - signature_enabled (checkbox) 未送信は false 補正
     */
    public function passedValidation(): void
    {
        if ($this->has('signature_html')) {
            $this->merge([
                'signature_html' => \App\Support\SignatureSanitizer::sanitize($this->input('signature_html')),
            ]);
        }
        if (!$this->has('signature_enabled')) {
            $this->merge(['signature_enabled' => false]);
        }
    }
}
