/**
 * Laravel's ValidateCsrfToken accepts the XSRF-TOKEN cookie echoed back as a
 * header. Inertia's own requests do this transparently, but a plain `fetch`
 * call to a JSON endpoint doesn't go through Inertia's client, so it has to
 * supply the header itself.
 */
export function csrfHeader(): Record<string, string> {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}
