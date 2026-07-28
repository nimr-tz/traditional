<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\Subtheme;
use App\Support\RegistrationWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WelcomeController extends Controller
{
    public function index(): Response
    {
        $conference = ConferenceSetting::allSettings();
        $feeCategories = FeeCategory::where('active', true)->orderBy('sort_order')->get(['label', 'amount', 'currency']);
        $canonicalUrl = route('home');
        $conferenceName = $conference->get('conference_name') ?: 'Traditional Medicine Scientific Conference';
        $startDate = $this->isoDate($conference->get('start_date'));
        $endDate = $this->isoDate($conference->get('end_date'));
        $dates = $this->dateRange($conference->get('start_date'), $conference->get('end_date'));
        $description = collect([$conference->get('theme'), $dates, $conference->get('venue')])
            ->filter()
            ->implode(' · ');
        $title = collect([
            $conference->get('edition_number') ? $conference->get('edition_number').' '.$conferenceName : $conferenceName,
            $conference->get('conference_year'),
        ])->filter()->implode(' · ');
        $imageUrl = Vite::asset('resources/images/traditional-medicine-hero-1200.webp');

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $conferenceName,
            'description' => $description,
            'url' => $canonicalUrl,
            'image' => [$imageUrl],
            ...($startDate ? ['startDate' => $startDate] : []),
            ...($endDate ? ['endDate' => $endDate] : []),
            ...($conference->get('venue') ? [
                'location' => [
                    '@type' => 'Place',
                    'name' => $conference->get('venue'),
                    'address' => $conference->get('venue'),
                ],
            ] : []),
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'National Institute for Medical Research',
                ...($conference->get('website') ? ['url' => $conference->get('website')] : []),
            ],
            ...($feeCategories->isNotEmpty() ? [
                'offers' => $feeCategories->map(fn (FeeCategory $fee) => [
                    '@type' => 'Offer',
                    'name' => $fee->label,
                    'price' => $fee->amount,
                    'priceCurrency' => $fee->currency,
                    'url' => route('register'),
                ])->values()->all(),
            ] : []),
        ];

        return Inertia::render('welcome', [
            'conference' => $conference,
            'subthemes' => Subtheme::where('active', true)->orderBy('sort_order')->get(['title', 'description']),
            'feeCategories' => $feeCategories,
            'registrationWindow' => RegistrationWindow::toArray(),
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonicalUrl' => $canonicalUrl,
                'imageUrl' => $imageUrl,
                'structuredData' => $structuredData,
            ],
        ]);
    }

    private function isoDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function displayDate(?string $value): ?string
    {
        $date = $this->isoDate($value);

        return $date ? CarbonImmutable::parse($date)->format('j F Y') : $value;
    }

    private function dateRange(?string $start, ?string $end): ?string
    {
        if ($start && $end && $start !== $end) {
            return $this->displayDate($start).' – '.$this->displayDate($end);
        }

        return $this->displayDate($start ?: $end);
    }
}
