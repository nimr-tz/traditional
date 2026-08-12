<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\User;
use App\Services\CertificateIssuer;
use App\Support\CertificateWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Claiming a certificate without an account.
 *
 * Walk-ins registered at the venue desk have a password nobody ever told them,
 * and many have no email address at all — so "sign in to download it" excludes
 * exactly the people most likely to want one. They identify themselves here
 * instead.
 *
 * Two details are required, never just a name. A name alone would let anybody
 * download a stranger's certificate simply by knowing who attended, and the
 * badge code or a contact detail is something the holder has and a passer-by
 * does not. Matching is deliberately narrow: an exact-ish name plus one more
 * fact, and a single unambiguous match, or nothing.
 */
class CertificateClaimController extends Controller
{
    public function create(): View
    {
        return view('certificates.claim', [
            'window' => CertificateWindow::toArray(),
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ]);
    }

    public function store(Request $request, CertificateIssuer $issuer): Response|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'proof' => ['required', 'string', 'max:255'],
        ], [], [
            'proof' => 'badge code, phone number or email',
        ]);

        $matches = $this->findBy($validated['name'], $validated['proof']);

        if ($matches->count() !== 1) {
            // The same message whether nothing matched or several did: telling a
            // stranger "that name exists but your other detail is wrong" turns
            // this form into a way of confirming who attended.
            return back()
                ->withInput($request->only('name'))
                ->with('error', 'We could not match those details to an attendance record. Check the spelling of the name, and use the badge code, phone number or email given at registration.');
        }

        $user = $matches->first();

        if ($reason = $issuer->blockedReason($user)) {
            return back()->withInput($request->only('name'))->with('error', $reason);
        }

        return $issuer->render($user)->download($issuer->filenameFor($user));
    }

    /**
     * Registrants whose name matches and who can produce one more matching
     * detail — their badge code, phone number, or email.
     *
     * @return Collection<int, User>
     */
    private function findBy(string $name, string $proof)
    {
        $name = trim($name);
        $proof = trim($proof);

        // Compared without spaces so "+255 712 000 111" matches "+255712000111".
        $bareProof = preg_replace('/\s+/', '', $proof);

        return User::withRole(User::ROLE_USER)
            ->where('name', 'like', "%{$name}%")
            ->where(fn (Builder $query) => $query
                ->whereRaw('replace(registration_code, " ", "") = ?', [strtoupper($bareProof)])
                ->orWhereRaw('replace(phone, " ", "") = ?', [$bareProof])
                ->orWhere('email', $proof))
            ->limit(2)
            ->get();
    }
}
