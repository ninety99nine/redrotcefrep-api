@extends('layouts.app')

@section('content')
    <div class="m-auto">

        <!-- Successful Payment Icon -->
        <svg class="text-green-600 w-20 h-20 mx-auto my-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
        </svg>

        <img class="w-1/4 m-auto" src="{{ asset('/images/celebration.png') }}">

        <!-- Instructions -->
        <div class="text-center">
            <h3 class="md:text-2xl text-base text-gray-900 font-semibold text-center">Yay! Payment Successful</h3>
            <p class="text-gray-600 my-2">Thank you for completing your secure online payment.</p>
            <p>Get your next order on <span class="font-bold">{{ @Config::get('name', 'Perfect Order'); }}</span> 😁👌</p>

            <div class="w-1/2 p-8 mt-8 mb-16 mx-auto bg-green-50 shadow-sm border border-dotted rounded-sm">
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
        //  window.location.href = "{{ $redirect }}";

    </script>

@endsection
