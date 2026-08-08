Your TMSC registration category has been updated

Dear {{ $recipientName }},

Thank you for registering for the {{ $conferenceName }}, and for taking the time to submit your supporting document.

We are sorry to say that your registration does not qualify for the student rate. Please do not read this as any judgement on your studies or your standing — the student rate covers a narrower group than the word suggests, and many registrants who are genuinely studying, including doctoral candidates, fall outside it.

Note from the review team:
{{ $notes }}

So that nothing is left for you to sort out, we have already moved your registration for you:

Registration category: {{ $categoryLabel }}
@if ($feeAmount)
Amount payable: {{ $feeAmount }}
@endif

You do not need to submit another document, and you do not need to change anything yourself.
@if ($keptControlNumber)

Your control number {{ $keptControlNumber }} remains valid. Please go ahead and pay it as usual.
@else

Please request your control number from your payment page when you are ready.
@endif

Your payment page:
{!! $paymentUrl !!}

If you believe the student rate should apply to you, you are welcome to upload a different document from your profile and we will review it again. Please note that your registration stays at the participant rate while we do — so you are free to pay it in the meantime and you will not lose your place. If the new document is approved, we will move you onto the student rate ourselves and cancel the participant bill.

Kind regards,
{{ config('app.name') }} Team
