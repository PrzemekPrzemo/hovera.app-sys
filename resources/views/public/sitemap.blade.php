<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/transport/zapytanie') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/przewoznicy') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/transport/calculator') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@foreach ($transporters as $tenant)
    <url>
        <loc>{{ url('/t/'.$tenant->slug) }}</loc>
        <lastmod>{{ optional($tenant->updated_at)->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
@foreach ($stables as $tenant)
    <url>
        <loc>{{ url('/s/'.$tenant->slug) }}</loc>
        <lastmod>{{ optional($tenant->updated_at)->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
@endforeach
</urlset>
