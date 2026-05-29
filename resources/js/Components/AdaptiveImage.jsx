export default function AdaptiveImage({
    src,
    alt = '',
    fit = 'cover',
    className = '',
    wrapperClass = '',
    loading = 'lazy',
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
                className={`${className} ${fitClass}`}
            />
        </div>
    );
}
