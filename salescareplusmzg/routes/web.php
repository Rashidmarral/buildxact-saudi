<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CertificationController as AdminCertificationController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\GalleryImageController as AdminGalleryImageController;
use App\Http\Controllers\Admin\NavItemController as AdminNavItemController;
use App\Http\Controllers\Admin\NewsletterSubscriberController as AdminNewsletterSubscriberController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PageSectionController as AdminPageSectionController;
use App\Http\Controllers\Admin\PrincipalController as AdminPrincipalController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TeamMemberController as AdminTeamMemberController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/principals', [PrincipalController::class, 'index'])->name('principals');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{product:slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/services', [ServiceController::class, 'index'])->name('services');
Route::get('/quality', [QualityController::class, 'index'])->name('quality');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
Route::get('/careers', [CareersController::class, 'index'])->name('careers');
Route::get('/faq', [FaqController::class, 'index'])->name('faq');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.store');

/*
|--------------------------------------------------------------------------
| Admin (CMS)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('product-categories', AdminProductCategoryController::class)->except('show');
        Route::resource('products', AdminProductController::class)->except('show');
        Route::resource('principals', AdminPrincipalController::class)->except('show');
        Route::resource('testimonials', AdminTestimonialController::class)->except('show');
        Route::resource('certifications', AdminCertificationController::class)->except('show');
        Route::resource('team-members', AdminTeamMemberController::class)->except('show');
        Route::resource('faqs', AdminFaqController::class)->except('show');
        Route::resource('gallery-images', AdminGalleryImageController::class)->except('show');
        Route::resource('nav-items', AdminNavItemController::class)->except('show');

        Route::resource('pages', AdminPageController::class)->except('show');
        Route::get('pages/{page}/sections/create', [AdminPageSectionController::class, 'create'])->name('pages.sections.create');
        Route::post('pages/{page}/sections', [AdminPageSectionController::class, 'store'])->name('pages.sections.store');
        Route::get('pages/{page}/sections/{section}/edit', [AdminPageSectionController::class, 'edit'])->name('pages.sections.edit');
        Route::put('pages/{page}/sections/{section}', [AdminPageSectionController::class, 'update'])->name('pages.sections.update');
        Route::delete('pages/{page}/sections/{section}', [AdminPageSectionController::class, 'destroy'])->name('pages.sections.destroy');

        Route::get('settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');

        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');

        Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
        Route::delete('contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

        Route::get('newsletter-subscribers', [AdminNewsletterSubscriberController::class, 'index'])->name('newsletter-subscribers.index');
        Route::delete('newsletter-subscribers/{newsletterSubscriber}', [AdminNewsletterSubscriberController::class, 'destroy'])->name('newsletter-subscribers.destroy');
    });
});

// Custom admin-authored pages — kept last so it never shadows a named route above.
Route::get('/{slug}', [PageController::class, 'show'])->name('page.show');
