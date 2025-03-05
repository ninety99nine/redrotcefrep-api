@extends('layouts.app')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <div class="mx-auto w-full p-8 lg:w-2/3 lg:p-20">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/logo-black-transparent.png') }}" alt="Perfect Order Logo" class="h-24 mx-auto">
        </div>
        <h1 class="text-3xl font-bold mb-4">Privacy Policy</h1>
        <p class="text-gray-700 leading-relaxed mb-6">
            Welcome to Perfect Order ("we," "our," or "us"). Your privacy is important to us, and this Privacy Policy explains how we collect, use, and protect your information when you use our services.
        </p>
        <h2 class="text-xl font-semibold mt-6 mb-2">1. Information We Collect</h2>
        <ul class="list-disc list-inside text-gray-700 space-y-2 px-8">
            <li><strong>Personal Identification Information:</strong> Full name, first name, last name, email address, mobile number, WhatsApp number, USSD number, social media identifiers, and hashed password.</li>
            <li><strong>Demographic Information:</strong> Country, language, currency preferences, and address details.</li>
            <li><strong>Business and Store Data:</strong> Store name, alias, emoji, contact details, description, and store preferences.</li>
            <li><strong>Transaction Data:</strong> Order details, payment methods, transaction status, amounts, currencies, and subscription details.</li>
            <li><strong>Usage Data:</strong> Interaction timestamps, AI assistant usage metrics, product, promotion, and cart information.</li>
            <li><strong>Media and Content:</strong> Uploaded media files such as file name, path, size, and type.</li>
            <li><strong>Communications Data:</strong> SMS and email content, recipient information, and delivery status.</li>
            <li><strong>Verification Data:</strong> Mobile verification codes.</li>
            <li><strong>Workflow Automation Data:</strong> Workflow steps and settings.</li>
        </ul>
        <h2 class="text-xl font-semibold mt-6 mb-2">2. How We Use Your Information</h2>
        <p class="text-gray-700 leading-relaxed">
            We use your information to operate, personalize, and enhance our services, process transactions, communicate with you, improve customer experiences, perform analytics, and comply with legal obligations.
        </p>
        <h2 class="text-xl font-semibold mt-6 mb-2">3. Data Retention</h2>
        <p class="text-gray-700 leading-relaxed">
            User data is retained for 12 months post-account closure to comply with legal obligations. Transaction data is retained for 5 years in compliance with Botswana’s Income Tax Act.
        </p>
        <h2 class="text-xl font-semibold mt-6 mb-2">4. Sharing of Information</h2>
        <p class="text-gray-700 leading-relaxed">
            We may share your data with third-party services (e.g., payment processors, communication providers, and analytics tools), comply with legal requests, and for internal service improvement.
        </p>
        <h2 class="text-xl font-semibold mt-6 mb-2">5. User Rights</h2>
        <p class="text-gray-700 leading-relaxed">
            You can access, update, or delete your data by contacting support. You may also opt out of specific communications or submit data portability requests.
        </p>
    </div>
</div>
@endsection
