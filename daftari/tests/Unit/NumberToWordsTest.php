<?php

namespace Tests\Unit;

use App\Support\NumberToWords;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_zero(): void
    {
        $this->assertSame('صفر ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(0));
    }

    public function test_a_single_riyal(): void
    {
        $this->assertSame('واحد ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(1));
    }

    public function test_a_teen_number(): void
    {
        $this->assertSame('خمسة عشر ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(15));
    }

    public function test_thousands_and_hundreds_combine_with_the_conjunction(): void
    {
        $this->assertSame('ألف وخمسمائة ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(1500));
    }

    public function test_a_dual_thousand(): void
    {
        $this->assertSame('ألفان ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(2000));
    }

    public function test_three_to_ten_thousands_use_the_broken_plural(): void
    {
        $this->assertSame('ثلاثة آلاف ومائتان ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(3200));
    }

    public function test_eleven_and_above_thousands_use_the_accusative_singular(): void
    {
        $this->assertSame('واحد وعشرون ألفاً ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(21000));
    }

    public function test_a_dual_million(): void
    {
        $this->assertSame('مليونان ريال سعودي فقط لا غير', NumberToWords::arabicRiyals(2000000));
    }

    public function test_halalas_are_appended_after_the_riyal_amount(): void
    {
        $this->assertSame('ألف وخمسمائة ريال سعودي وخمسون هللة فقط لا غير', NumberToWords::arabicRiyals(1500.50));
    }

    public function test_the_english_form_of_a_round_thousand(): void
    {
        $this->assertSame('One Thousand Five Hundred Saudi Riyals Only', NumberToWords::englishRiyals(1500));
    }

    public function test_the_english_form_with_halalas(): void
    {
        $this->assertSame('One Thousand Five Hundred Saudi Riyals and Fifty Halalas Only', NumberToWords::englishRiyals(1500.50));
    }

    public function test_english_zero(): void
    {
        $this->assertSame('Zero Saudi Riyals Only', NumberToWords::englishRiyals(0));
    }
}
