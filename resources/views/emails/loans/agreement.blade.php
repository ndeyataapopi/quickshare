<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Agreement {{ $loan->reference }}</title>
</head>
<body style="color: #1f2937; font-family: Arial, sans-serif; line-height: 1.6;">
    <h1 style="color: #111827;">Your Loan Agreement</h1>
    <p>Hello {{ $loan->borrower->first_name }},</p>
    <p>Your loan application was submitted successfully. Your loan agreement is attached as a PDF.</p>

    <h2 style="color: #111827; font-size: 18px;">Loan Summary</h2>
    <table style="border-collapse: collapse; width: 100%; max-width: 560px;">
        <tr>
            <td style="border-bottom: 1px solid #e5e7eb; font-weight: bold; padding: 8px;">Loan Number</td>
            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $loan->reference }}</td>
        </tr>
        <tr>
            <td style="border-bottom: 1px solid #e5e7eb; font-weight: bold; padding: 8px;">Repayment Amount</td>
            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ config('loans.currency') }} {{ number_format((float) $loan->total_repayment, 2) }}</td>
        </tr>
        <tr>
            <td style="border-bottom: 1px solid #e5e7eb; font-weight: bold; padding: 8px;">Repayment Date</td>
            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $loan->repayment_date->format('d F Y') }}</td>
        </tr>
    </table>

    <p>Please retain the attached agreement for your records.</p>
    <p>Thank you for choosing {{ config('app.name') }}.</p>
</body>
</html>
