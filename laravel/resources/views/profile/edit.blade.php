@extends('layouts.app')

@section('title', 'Profile Settings - Rice')

@section('content')
<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-2xl font-black text-gray-900 tracking-tight mb-8">Profile Settings</h2>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- Phase 6-4: Agent 別メール署名 --}}
        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.signature-form')
            </div>
        </div>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</main>
@endsection
