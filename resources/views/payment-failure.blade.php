@extends('layouts.app')

@section('content')
    <div class="m-auto">

        <!-- Unsuccessful Payment Icon -->
        <svg class="text-yellow-600 w-20 h-20 mx-auto my-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
        </svg>

        <img class="w-1/4 m-auto" src="{{ asset('/images/sorry.png') }}">

        <!-- Instructions -->
        <div class="text-center">
            <h3 class="md:text-2xl text-base text-gray-900 font-semibold text-center">Payment Unsuccessful</h3>
            <p class="text-gray-600 my-2">{{ $failureReason }}</p>

            <div class="w-1/2 p-8 mt-8 mb-16 mx-auto bg-yellow-50 shadow-sm border border-dotted rounded-sm">
                <h1 class="md:text-2xl text-base text-gray-900 font-semibold text-center">Transaction #{{ $transaction->number }}</h1>
                <p class="mb-4">{{ $transaction->description }}</p>
                <p>
                    <span>Amount: </span>
                    <span>{{ $transaction->amount->amountWithCurrency }}</span>
                </p>
                <p>
                    <span>Status: </span>
                    <span>{{ $transaction->payment_status }}</span>
                </p>
            </div>
        </div>

    </div>

    <!-- JavaScript to trigger immediate redirect -->
    <script type="text/javascript">

        // Redirect to the provided URL
        window.location.href = "{{ $redirect }}";

    </script>

@endsection
