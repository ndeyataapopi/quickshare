<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Services\PlatformStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PublicController extends Controller
{
    public function __construct(
        protected PlatformStatisticsService $statisticsService,
    ) {}

    public function home()
    {
        $stats = $this->statisticsService->getStatistics();

        return view('home', compact('stats'));
    }

    public function about()
    {
        return view('about');
    }

    public function howItWorks()
    {
        return view('how-it-works');
    }

    public function faq()
    {
        return view('faq');
    }

    public function contact()
    {
        return view('contact');
    }

    public function privacy()
    {
        return view('privacy');
    }

    public function terms()
    {
        return view('terms');
    }

    public function borrow()
    {
        return view('borrow');
    }

    public function lend()
    {
        return view('lend');
    }

    public function support()
    {
        return view('support');
    }

    public function compliance()
    {
        return view('compliance');
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
            'g-recaptcha-response' => ['required', 'string'],
        ]);

        // Verify reCAPTCHA
        $recaptchaSecret = config('services.recaptcha.secret');
        if ($recaptchaSecret) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $validated['g-recaptcha-response'],
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success', false)) {
                throw ValidationException::withMessages([
                    'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
                ]);
            }
        }

        // Send email
        Mail::to(config('mail.contact_address', 'support@quickshare.nepticgroup.com'))
            ->send(new ContactMessage([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
            ]));

        return back()->with('success', 'Your message has been sent. We will get back to you soon.');
    }

    public function sitemap()
    {
        $urls = [
            ['url' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => route('about'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => route('how-it-works'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => route('borrow'), 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['url' => route('lend'), 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['url' => route('faq'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => route('contact'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => route('support'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => route('compliance'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => route('terms'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $content .= "  <url>\n";
            $content .= "    <loc>{$url['url']}</loc>\n";
            $content .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $content .= "    <priority>{$url['priority']}</priority>\n";
            $content .= "  </url>\n";
        }

        $content .= '</urlset>';

        return response($content, 200, ['Content-Type' => 'application/xml']);
    }
}
