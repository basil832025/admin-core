# Guest Page Cache

This project is prepared for guest HTML page caching on selected frontend routes.

## Current cacheable routes

- `/`
- `/pies`
- `/ru`
- `/en`
- `/ru/pies`
- `/en/pies`

These responses now include:

- `X-Page-Cache-Candidate: guest`
- `Cache-Control: public, max-age=60, s-maxage=300`
- no `Set-Cookie` for first-time anonymous GET requests

## Guest detection

Cache only when all of the following are true:

- request method is `GET` or `HEAD`
- response header `X-Page-Cache-Candidate` equals `guest`
- no session cookie is present
- no auth/profile/cart/checkout cookies are present

Primary Laravel session cookie:

- `laravel_session`

## Nginx example

Adjust paths and upstream names to match the server.

```nginx
fastcgi_cache_path /var/cache/nginx/myadmin levels=1:2 keys_zone=MYADMIN_HTML:100m inactive=30m max_size=2g;

map $request_method $skip_cache_by_method {
    default 1;
    GET 0;
    HEAD 0;
}

map $request_uri $skip_cache_by_path {
    default 1;
    ~^/(|ru|en)$ 0;
    ~^/(ru|en/)?pies$ 0;
}

map $http_cookie $skip_cache_by_cookie {
    default 0;
    ~*(laravel_session|XSRF-TOKEN|remember_|login_|auth|filament|cart|favorite|favorites|checkout) 1;
}

map "$skip_cache_by_method$skip_cache_by_path$skip_cache_by_cookie" $skip_cache {
    default 1;
    000 0;
}

server {
    location / {
        include fastcgi_params;
        fastcgi_pass php_upstream;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;

        fastcgi_cache_bypass $skip_cache;
        fastcgi_no_cache $skip_cache;

        fastcgi_cache MYADMIN_HTML;
        fastcgi_cache_key "$scheme$request_method$host$request_uri";
        fastcgi_cache_valid 200 5m;
        fastcgi_cache_valid 301 302 1m;
        fastcgi_ignore_headers Set-Cookie;

        add_header X-FastCGI-Cache $upstream_cache_status always;
    }
}
```

Important:

- keep cache enabled only for routes listed in `skip_cache_by_path`
- do not cache `/cart`, `/favorites`, `/checkout`, `/auth`, `/profile`, `/admin`
- if backend returns `Set-Cookie`, make sure cache bypass stays on for those cases

## Cloudflare example

Create a Cache Rule for guest HTML only.

Expression idea:

```text
(http.request.method in {"GET" "HEAD"})
and (
  http.request.uri.path eq "/"
  or http.request.uri.path eq "/pies"
  or http.request.uri.path eq "/ru"
  or http.request.uri.path eq "/en"
  or http.request.uri.path eq "/ru/pies"
  or http.request.uri.path eq "/en/pies"
)
and not len(http.request.cookies["laravel_session"]) gt 0
and not len(http.request.cookies["XSRF-TOKEN"]) gt 0
```

Recommended rule action:

- `Eligible for cache: On`
- `Edge TTL: 5 minutes`
- `Browser TTL: Respect origin`

Add a second bypass rule above it for:

- `/admin*`
- `/auth*`
- `/profile*`
- `/cart*`
- `/favorites*`
- `/checkout*`

## Verification checklist

Anonymous browser / curl:

```bash
curl -I https://example.com/
curl -I https://example.com/pies
curl -I https://example.com/ru
curl -I https://example.com/en/pies
```

Expected origin headers:

- `X-Page-Cache-Candidate: guest`
- `Cache-Control: public, max-age=60, s-maxage=300`
- no `Set-Cookie`

Expected proxy behavior:

- first request: `MISS`
- second request: `HIT`

## Safe next expansion

After validating the routes above, the same approach can be extended to:

- catalog category pages
- blog listing and article pages
- static pages without personalized HTML
