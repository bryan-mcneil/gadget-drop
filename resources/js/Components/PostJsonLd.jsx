export default function PostJsonLd({ post }) {
    const seo      = post.seo_meta ?? {};
    const base     = typeof window !== 'undefined' ? window.location.origin : '';
    const postUrl  = seo.canonical_url || `${base}/posts/${post.slug}`;
    const logoUrl  = `${base}/favicon.svg`;

    const graph = [];

    /* ── BlogPosting ─────────────────────────────────────────── */
    const article = {
        '@type':             'BlogPosting',
        '@id':               `${postUrl}#article`,
        headline:            post.title,
        url:                 postUrl,
        mainEntityOfPage:    { '@type': 'WebPage', '@id': postUrl },
        datePublished:       post.published_at ?? undefined,
        dateModified:        post.updated_at   ?? undefined,
        publisher: {
            '@type': 'Organization',
            name:    'GadgetDrop',
            logo:    { '@type': 'ImageObject', url: logoUrl },
        },
    };

    const description = seo.meta_description || post.excerpt;
    if (description) article.description = description;

    const heroImage = seo.og_image || post.featured_image;
    if (heroImage) article.image = { '@type': 'ImageObject', url: heroImage };

    if (post.user?.name) {
        article.author = { '@type': 'Person', name: post.user.name };
    }

    const keywords = post.tags?.map((t) => t.name).join(', ');
    if (keywords) article.keywords = keywords;

    graph.push(article);

    /* ── Product + Offer (one per attached product) ──────────── */
    post.products?.forEach((p) => {
        const product = {
            '@type': 'Product',
            name:    p.name,
            offers: {
                '@type':        'Offer',
                priceCurrency:  'USD',
                availability:   'https://schema.org/InStock',
                url:            `${base}/out/${p.id}`,
            },
        };

        if (p.description) product.description = p.description;
        if (p.image_url)   product.image       = p.image_url;
        if (p.asin)        product.sku         = p.asin;
        if (p.price)       product.offers.price = String(p.price);

        graph.push(product);
    });

    /* ── BreadcrumbList ──────────────────────────────────────── */
    const crumbs = [
        { '@type': 'ListItem', position: 1, name: 'Home', item: base },
    ];

    const firstCat = post.categories?.[0];
    if (firstCat) {
        crumbs.push({
            '@type':    'ListItem',
            position:   2,
            name:       firstCat.name,
            item:       `${base}/category/${firstCat.slug}`,
        });
        crumbs.push({ '@type': 'ListItem', position: 3, name: post.title, item: postUrl });
    } else {
        crumbs.push({ '@type': 'ListItem', position: 2, name: post.title, item: postUrl });
    }

    graph.push({ '@type': 'BreadcrumbList', itemListElement: crumbs });

    /* ── Render ──────────────────────────────────────────────── */
    const json = JSON.stringify({ '@context': 'https://schema.org', '@graph': graph })
        // Prevent </script> in any field value from breaking the tag
        .replace(/<\/script>/gi, '<\\/script>');

    return (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{ __html: json }}
        />
    );
}
