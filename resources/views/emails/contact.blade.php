<x-mail::message>
# New Contact Form Submission

A user has submitted the contact form on QuickShare.

<x-mail::panel>
**Name:** {{ $data['name'] }}<br>
**Email:** {{ $data['email'] }}<br>
**Subject:** {{ $data['subject'] }}
</x-mail::panel>

<x-mail::panel>
**Message:**
{{ $data['message'] }}
</x-mail::panel>

<x-mail::button :url="config('app.url')" color="primary">
Go to QuickShare
</x-mail::button>

<x-mail::outro>
This message was sent from the QuickShare contact form.
</x-mail::outro>
