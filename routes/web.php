<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ImageController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\TechTipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

// Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Public site
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/news', [PublicController::class, 'news'])->name('news');
Route::get('/search', [PublicController::class, 'search'])->name('search');
Route::get('/posts/{post:slug}', [PublicController::class, 'show'])->name('posts.show');
Route::get('/category/{category:slug}', [PublicController::class, 'category'])->name('category');
Route::get('/out/{product}', [PublicController::class, 'redirect'])->name('affiliate.redirect');
Route::get('/s/{post:share_code}', [PublicController::class, 'shortlink'])->name('post.shortlink');
Route::get('/author/{user:slug}', [PublicController::class, 'author'])->name('author');
Route::get('/about',   [PublicController::class, 'about'])->name('about');
Route::get('/privacy', [PublicController::class, 'privacy'])->name('privacy');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/cookies', [PublicController::class, 'cookies'])->name('cookies');
Route::get('/terms',   [PublicController::class, 'terms'])->name('terms');
Route::post('/subscribe', [SubscriberController::class, 'store'])->name('subscribe');
Route::get('/unsubscribe', [SubscriberController::class, 'showUnsubscribe'])->name('unsubscribe');
Route::post('/unsubscribe', [SubscriberController::class, 'destroyByEmail'])->name('unsubscribe.email');
Route::get('/unsubscribe/{token}', [SubscriberController::class, 'destroyByToken'])->name('unsubscribe.token');

// Admin panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('posts', PostController::class)->except('show');
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit']);
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
    Route::post('images', [ImageController::class, 'store'])->name('images.store');
    Route::get('tech-tips', [TechTipController::class, 'index'])->name('tech-tips.index');
    Route::get('tech-tips/search', [TechTipController::class, 'search'])->name('tech-tips.search');
    Route::get('tech-tips/prepare', [TechTipController::class, 'prepare'])->name('tech-tips.prepare');
    Route::post('tech-tips/generate', [TechTipController::class, 'generate'])->name('tech-tips.generate');
    Route::get('news', [NewsController::class, 'index'])->name('news.index');
    Route::post('news/prompt', [NewsController::class, 'buildPrompt'])->name('news.prompt');
    Route::post('news/generate', [NewsController::class, 'generate'])->name('news.generate');
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
