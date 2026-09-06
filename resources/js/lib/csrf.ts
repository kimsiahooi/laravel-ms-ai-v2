/**
 * Laravel's CSRF token, in the form its middleware checks back.
 *
 * Laravel writes `XSRF-TOKEN` as a readable cookie precisely so a browser can echo it as
 * the `X-XSRF-TOKEN` header, which is how `PreventRequestForgery` recognises a request
 * the app itself made. Inertia's own client does this for every visit it sends; anything
 * bypassing Inertia — a background save, a lookup — has to do it by hand.
 *
 * `decodeURIComponent` is not optional: the value is a base64 payload that routinely
 * contains `=` and `+`, and Laravel percent-encodes it on the way out. Sending it raw
 * produces a token that fails to match, which surfaces as an unexplained 419.
 *
 * Returns an empty string when the cookie is absent — a browser refusing cookies, or a
 * session that has since gone. Nothing is thrown, because the request that follows will
 * be refused anyway and the caller's own failure path is the right place to say so.
 */
export function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match?.[1] === undefined ? '' : decodeURIComponent(match[1]);
}
