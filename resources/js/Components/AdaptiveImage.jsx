export default function AdaptiveImage({
    src,
    alt = '',
    fit = 'cover',
    position = 'center center',
    className = '',
    wrapperClass = '',
    loading = 'lazy',
    fetchpriority,
    width,
    height,
}) {
    const fitClass = fit === 'contain'
        ? 'object-contain bg-gray-50'
        : 'object-cover';

    return (
        <div className={wrapperClass}>
            <img
                src={src}
                alt={alt}
                loading={loading}
                fetchpriority={fetchpriority}
                width={width}
                height={height}
                className={`${className} ${fitClass}`}
                style={fit !== 'contain' ? { objectPosition: position } : undefined}
            />
        </div>
    );
}
