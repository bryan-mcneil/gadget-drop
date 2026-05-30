const AMAZON_SHIPPING = {
    '@type':               'OfferShippingDetails',
    shippingRate:          { '@type': 'MonetaryAmount', value: '0', currency: 'USD' },
    shippingDestination:   { '@type': 'DefinedRegion', addressCountry: 'US' },
    deliveryTime: {
        '@type':       'ShippingDeliveryTime',
        handlingTime:  { '@type': 'QuantitativeValue', minValue: 0, maxValue: 1, unitCode: 'DAY' },
        transitTime:   { '@type': 'QuantitativeValue', minValue: 2, maxValue: 5, unitCode: 'DAY' },
    },
};

const AMAZON_RETURN_POLICY = {
    '@type':               'MerchantReturnPolicy',
    applicableCountry:     'US',
    returnPolicyCategory:  'https://schema.org/MerchantReturnFiniteReturnWindow',
    merchantReturnDays:    30,
    returnMethod:          'https://schema.org/ReturnByMail',
    returnFees:            'https://schema.org/FreeReturn',
};

export default function PostJsonLd({ post }) {
    const seo     = post.seo_meta ?? {};
    const base    = typeof window !== 'undefined' ? window.location.origin : '';
    const postUrl = seo.canonical_url || `${base}/posts/${post.slug}`;
    const logoUrl = `${base}/favicon.svg`;

    const graph = [];

    /* ── BlogPosting ─────────────────────────────────────────── */
    const article = {
        '@type':          'BlogPosting',
        '@id':            `${postUrl}#article`,
        headline:         post.title,
        url:              postUrl,
        mainEntityOfPage: { '@type': 'WebPage', '@id': postUrl },
        datePublished:    post.published_at_iso ?? undefined,
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

    if (post.user?.name) article.author = {
        '@type': 'Person',
        name: post.user.name,
        url:  `${base}/author/${post.user.slug}`,
    };

    const keywords = post.tags?.map((t) => t.name).join(', ');
    if (keywords) article.keywords = keywords;

    graph.push(article);

    /* ── Product + Offer + Reviews ───────────────────────────── */
    post.products?.forEach((p) => {
        const product = {
            '@type': 'Product',
            name:    p.name,
            hasMerchantReturnPolicy: AMAZON_RETURN_POLICY,
            offers: {
                '@type':          'Offer',
                priceCurrency:    'USD',
                availability:     'https://schema.org/InStock',
                itemCondition:    'https://schema.org/NewCondition',
                url:              `${base}/out/${p.id}`,
                seller:           { '@type': 'Organization', name: 'Amazon' },
                shippingDetails:  AMAZON_SHIPPING,
            },
        };

        if (p.brand)        product.brand       = { '@type': 'Brand', name: p.brand };
        if (p.description)  product.description = p.description;
        if (p.image_url)    product.image       = p.image_url;
        if (p.price != null) product.offers.price = Number(p.price);

        /* Amazon aggregate rating */
        if (p.amazon_rating && p.amazon_review_count) {
            product.aggregateRating = {
                '@type':      'AggregateRating',
                ratingValue:  p.amazon_rating,
                reviewCount:  p.amazon_review_count,
                bestRating:   5,
                worstRating:  1,
            };
        }

        /* Editorial review */
        if (post.rating) {
            const review = {
                '@type':  'Review',
                author:   {
                    '@type': 'Person',
                    name: post.user?.name ?? 'GadgetDrop Editorial',
                    url:  post.user?.slug ? `${base}/author/${post.user.slug}` : undefined,
                },
                datePublished: post.published_at_iso,
                reviewRating: {
                    '@type':      'Rating',
                    ratingValue:  Number(post.rating),
                    bestRating:   5,
                    worstRating:  1,
                },
            };

            if (post.pros?.length > 0) {
                review.positiveNotes = {
                    '@type': 'ItemList',
                    itemListElement: post.pros.map((pro, i) => ({
                        '@type': 'ListItem', position: i + 1, name: pro,
                    })),
                };
            }

            if (post.cons?.length > 0) {
                review.negativeNotes = {
                    '@type': 'ItemList',
                    itemListElement: post.cons.map((con, i) => ({
                        '@type': 'ListItem', position: i + 1, name: con,
                    })),
                };
            }

            product.review = review;
        }

        graph.push(product);
    });

    /* ── BreadcrumbList ──────────────────────────────────────── */
    const crumbs = [
        { '@type': 'ListItem', position: 1, name: 'Home', item: base },
    ];

    const firstCat = post.categories?.[0];
    if (firstCat) {
        crumbs.push({ '@type': 'ListItem', position: 2, name: firstCat.name, item: `${base}/category/${firstCat.slug}` });
        crumbs.push({ '@type': 'ListItem', position: 3, name: post.title, item: postUrl });
    } else {
        crumbs.push({ '@type': 'ListItem', position: 2, name: post.title, item: postUrl });
    }

    graph.push({ '@type': 'BreadcrumbList', itemListElement: crumbs });

    /* ── Render ──────────────────────────────────────────────── */
    const json = JSON.stringify({ '@context': 'https://schema.org', '@graph': graph })
        .replace(/<\/script>/gi, '<\\/script>');

    return (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{ __html: json }}
        />
    );
}
