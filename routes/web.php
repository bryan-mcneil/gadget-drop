<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DailyDropController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImageController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\DealsController;
use App\Http\Controllers\DropPriceController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

// Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Search-engine discovery: IndexNow key file + llms.txt site map.
Route::get('/indexnow.txt', [SearchController::class, 'indexNowKey'])->name('indexnow.key');
Route::get('/llms.txt', [SearchController::class, 'llms'])->name('llms');

// Public site
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/news', [PublicController::class, 'news'])->name('news');
Route::get('/search', [PublicController::class, 'search'])->name('search');
Route::get('/posts/{post:slug}', [PublicController::class, 'show'])->name('posts.show');
Route::get('/og/posts/{post:slug}.jpg', [OgImageController::class, 'post'])->name('og.posts.show');
Route::get('/og/default.jpg', [OgImageController::class, 'default'])->name('og.default');
Route::get('/og/preview', [OgImageController::class, 'preview'])->name('og.preview');
// Retired category slugs (consolidated into bigger hubs) → 301 to their target.
// Registered before the catch-all category route so they win.
foreach (config('site.category_map', []) as $oldCategorySlug => $newCategorySlug) {
    Route::permanentRedirect("/category/{$oldCategorySlug}", "/category/{$newCategorySlug}");
}
Route::get('/category/{category:slug}', [PublicController::class, 'category'])->name('category');
Route::get('/tag/{tag:slug}', [PublicController::class, 'tag'])->name('tag');
Route::get('/deals', [DealsController::class, 'index'])->name('deals');
Route::get('/drop-price', [DropPriceController::class, 'index'])->name('drop-price.index');
Route::get('/drop-price/{puzzle:puzzle_number}', [DropPriceController::class, 'show'])->name('drop-price.show');
Route::get('/out/{product}', [PublicController::class, 'redirect'])->name('affiliate.redirect');
Route::get('/s/{post:share_code}', [PublicController::class, 'shortlink'])->name('post.shortlink');
// Legacy fictional-persona author URLs → 301 to the single real author.
foreach (config('site.legacy_author_slugs', []) as $legacyAuthorSlug) {
    Route::permanentRedirect("/author/{$legacyAuthorSlug}", '/author/'.config('site.author.slug'));
}
Route::get('/author/{user:slug}', [PublicController::class, 'author'])->name('author');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/how-we-review', [PublicController::class, 'howWeReview'])->name('how-we-review');
Route::get('/privacy', [PublicController::class, 'privacy'])->name('privacy');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/cookies', [PublicController::class, 'cookies'])->name('cookies');
Route::get('/terms', [PublicController::class, 'terms'])->name('terms');
// Tools
Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
Route::get('/tools/json-validator', [ToolController::class, 'jsonValidator'])->name('tools.json-validator');
Route::get('/tools/js-css-minifier', [ToolController::class, 'jsCssMinifier'])->name('tools.js-css-minifier');
Route::get('/tools/image-editor', [ToolController::class, 'imageEditor'])->name('tools.image-editor');
Route::get('/tools/image-converter', [ToolController::class, 'imageConverter'])->name('tools.image-converter');
Route::get('/tools/background-remover', [ToolController::class, 'backgroundRemover'])->name('tools.background-remover');
Route::get('/tools/password-generator', [ToolController::class, 'passwordGenerator'])->name('tools.password-generator');
Route::get('/tools/base64-encoder', [ToolController::class, 'base64Encoder'])->name('tools.base64-encoder');
Route::get('/tools/color-palette', [ToolController::class, 'colorPalette'])->name('tools.color-palette');
Route::get('/tools/meta-tag-previewer', [ToolController::class, 'metaTagPreviewer'])->name('tools.meta-tag-previewer');
Route::permanentRedirect('/tools/image-cropper', '/tools/image-editor');
Route::permanentRedirect('/posts/mega-raichu-x-lands-in-pokémon-champions-as-the-game-hits-mobile', '/posts/mega-raichu-x-lands-in-pokémon-champions');
Route::permanentRedirect('/posts/samsung-galaxy-unpacked-set-for-july-22-watch-9-fold-8', '/posts/samsung-galaxy-unpacked-set-for-july-22');

// Server-side tools scaffold (Phase 2+)
Route::prefix('api/tools')->middleware(['throttle:tools'])->group(function () {
    // Phase 2 server-side tool endpoints go here
});

Route::post('/subscribe', [SubscriberController::class, 'store'])->name('subscribe');
Route::get('/unsubscribe', [SubscriberController::class, 'showUnsubscribe'])->name('unsubscribe');
Route::post('/unsubscribe', [SubscriberController::class, 'destroyByEmail'])->name('unsubscribe.email');
Route::get('/unsubscribe/{token}', [SubscriberController::class, 'destroyByToken'])->name('unsubscribe.token');

// Admin panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('posts', PostController::class)->except('show');
    Route::resource('products', ProductController::class)->except('show');
    Route::get('prices', [PriceController::class, 'index'])->name('prices.index');
    Route::post('prices/{product}', [PriceController::class, 'update'])->name('prices.update');
    Route::post('prices/{product}/confirm', [PriceController::class, 'confirm'])->name('prices.confirm');
    Route::get('social', [SocialController::class, 'index'])->name('social.index');
    Route::post('social/{socialPost}/posted', [SocialController::class, 'markPosted'])->name('social.posted');
    Route::post('social/{socialPost}/skip', [SocialController::class, 'skip'])->name('social.skip');
    Route::get('seo', [SeoController::class, 'index'])->name('seo.index');
    Route::post('seo/opportunities/{opportunity}', [SeoController::class, 'updateOpportunity'])->name('seo.opportunities.update');
    Route::post('seo/posts/{post}/reping', [SeoController::class, 'reping'])->name('seo.reping');
    Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit']);
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
    Route::post('images', [ImageController::class, 'store'])->name('images.store');
    Route::get('daily-drop', [DailyDropController::class, 'index'])->name('daily-drop.index');
    Route::post('daily-drop/generate', [DailyDropController::class, 'generate'])->name('daily-drop.generate');
    Route::get('newsletter', [NewsletterController::class, 'index'])->name('newsletter.index');
    Route::post('newsletter/test', [NewsletterController::class, 'sendTest'])->name('newsletter.test');
    Route::post('newsletter/send-all', [NewsletterController::class, 'sendAll'])->name('newsletter.send-all');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
