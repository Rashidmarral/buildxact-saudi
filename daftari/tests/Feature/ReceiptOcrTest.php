<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\ReceiptOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "upload a photo of the receipt" — ReceiptOcrService calls Google Cloud
 * Vision's TEXT_DETECTION and pulls a best-guess amount/date/vendor out of
 * the raw text it returns. Http::fake() stands in for the real API (no
 * network access in tests, and no real key configured either) so these
 * cover the parsing/wiring, not Vision's own OCR accuracy.
 */
class ReceiptOcrTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Scan Co.', 'slug' => 'scan-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function visionResponse(string $text): array
    {
        return ['responses' => [['fullTextAnnotation' => ['text' => $text]]]];
    }

    public function test_is_not_configured_without_an_api_key(): void
    {
        config(['services.google_vision.key' => null]);

        $this->assertFalse(app(ReceiptOcrService::class)->isConfigured());
    }

    public function test_scanning_without_a_key_fails_cleanly_without_a_network_call(): void
    {
        config(['services.google_vision.key' => null]);
        Http::fake();

        $result = app(ReceiptOcrService::class)->scan(UploadedFile::fake()->image('receipt.jpg'));

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_a_line_labeled_total_is_preferred_over_a_larger_unlabeled_number(): void
    {
        config(['services.google_vision.key' => 'test-key']);
        Http::fake(['vision.googleapis.com/*' => Http::response($this->visionResponse(
            "Fuel Station\n11/06/2026\nItem A  1,500.00\nTotal  230.00"
        ))]);

        $result = app(ReceiptOcrService::class)->scan(UploadedFile::fake()->image('receipt.jpg'));

        $this->assertTrue($result['success']);
        $this->assertSame(230.0, $result['amount']);
        $this->assertSame('2026-06-11', $result['date']);
        $this->assertSame('Fuel Station', $result['vendor_name']);
    }

    public function test_with_no_total_label_the_largest_number_is_used(): void
    {
        config(['services.google_vision.key' => 'test-key']);
        Http::fake(['vision.googleapis.com/*' => Http::response($this->visionResponse(
            "Grocery Mart\n2026-06-12\n15.50\n230.00"
        ))]);

        $result = app(ReceiptOcrService::class)->scan(UploadedFile::fake()->image('receipt.jpg'));

        $this->assertTrue($result['success']);
        $this->assertSame(230.0, $result['amount']);
    }

    public function test_a_vision_api_error_fails_cleanly(): void
    {
        config(['services.google_vision.key' => 'test-key']);
        Http::fake(['vision.googleapis.com/*' => Http::response(['error' => ['message' => 'Bad request']], 400)]);

        $result = app(ReceiptOcrService::class)->scan(UploadedFile::fake()->image('receipt.jpg'));

        $this->assertFalse($result['success']);
    }

    public function test_the_scan_endpoint_requires_an_image(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.expenses.scan-receipt'), [
            'file' => UploadedFile::fake()->create('statement.csv', 10),
        ])->assertSessionHasErrors('file');
    }

    public function test_the_scan_endpoint_returns_the_parsed_fields_as_json(): void
    {
        config(['services.google_vision.key' => 'test-key']);
        Http::fake(['vision.googleapis.com/*' => Http::response($this->visionResponse('Fuel Station\n2026-06-11\nTotal 230.00'))]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.expenses.scan-receipt'), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_the_scan_box_only_shows_on_a_new_expense_when_configured(): void
    {
        $owner = $this->makeOwner();

        config(['services.google_vision.key' => null]);
        $this->actingAs($owner)->get(route('app.expenses.create'))->assertDontSee(__('Scan a receipt'));

        config(['services.google_vision.key' => 'test-key']);
        $this->actingAs($owner)->get(route('app.expenses.create'))->assertSee(__('Scan a receipt'));
    }
}
