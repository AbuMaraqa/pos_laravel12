<?php

namespace App\Livewire\Pages\Product;

use App\Jobs\SyncProduct;
use App\Models\Product;
use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth; // تأكد من وجود هذا السطر
use App\Models\Inventory; // <-- أضفنا هذا
use App\Enums\InventoryType; // <-- تم التأكد منه
use Livewire\Attributes\Computed;
use Livewire\Attributes\Isolate;
use Livewire\Component;
use Livewire\Attributes\Url;
use Masmerise\Toaster\Toaster;
use PDF;

#[Isolate]
class Index extends Component
{
    #[Url(as: 'page')]
    public int $page = 1;

    #[Url]
    public string $search = '';

    public $categoryId = null;
    public $categories = [];

    public int $perPage = 10;
    public int $total = 0;

    public $product = [];
    public $variations = [];
    public $quantities = [];
    public $originalQuantities = []; // <-- أضفنا هذه لحفظ الكميات الأصلية

    /**
     * @var int|null $qtyToAdd
     * هذا الحقل لربط المدخل الخاص بـ "إضافة كمية للكل"
     */
    public $qtyToAdd = null;

    public $productVariations = [];
    public $roles = [];
    public $variationValues = [];
    public $productData = [];
    public $parentRoleValues = [];

    public $price = 0;
    public $sale_price = 0;
    public $main_price = 0;
    public $main_sale_price = 0;

    public $showVariationTable = false;

    protected WooCommerceService $wooService;

    public $columnPrices = []; // <-- أضف هذه الخاصية الجديدة


    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount(): void
    {
        $response = $this->wooService->getCategories(['parent' => 0]);
//        dd($this->wooService->getProducts());
        $this->categories = $response['data'] ?? $response;
    }

    /**
     * يتم استدعاؤها عند تغيير قيمة البحث
     * تعيد تعيين الصفحة إلى الأولى لعرض نتائج البحث الجديدة
     * يدعم البحث بالاسم والباركود (SKU) والـ ID
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function resetCategory(): void
    {
        $this->categoryId = null;
        $this->page = 1;
    }

    public function updateShowVariationTable(): void
    {
        $this->showVariationTable = !$this->showVariationTable;
    }

    public function setCategory($categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->page = 1;
    }

    public function openPrintBarcodeModal($productId)
    {
        $product = $this->wooService->getProductsById($productId);
        $this->product = $product;
        $this->quantities = ['main' => 1];
        $this->variations = [];

        foreach ($product['variations'] ?? [] as $variationId) {
            $variation = $this->wooService->getProductsById($variationId);
            $this->variations[] = $variation;
            $this->quantities[$variationId] = 1;
        }

        $this->modal('barcode-product-modal')->show();
    }

    public function openStockQtyModal($productId)
    {
        $product = $this->wooService->getProductsById($productId);
        $this->product = $product;

        // تصفير المصفوفات قبل البدء
        $this->quantities = ['main' => 1];
        $this->originalQuantities = ['main' => 1]; // <-- تصفير المصفوفة الجديدة
        $this->variations = [];

        foreach ($product['variations'] ?? [] as $variationId) {
            $variation = $this->wooService->getProductsById($variationId);
            $this->variations[] = $variation;

            // نقوم بتعبئة المصفوفتين بالكمية الحالية
            $currentStock = $variation['stock_quantity'] ?? 0;
            $this->quantities[$variationId] = $currentStock;
            $this->originalQuantities[$variationId] = $currentStock; // <-- حفظ الكمية الأصلية
        }

        $this->modal('stock-qty-product-modal')->show();
    }

    public function changeQty($qty)
    {
        // 1. التحقق من الكمية المدخلة وتحويلها إلى رقم صحيح
        $qtyToAdd = (int) $qty;

        // إذا كانت الكمية المدخلة 0 أو أقل، لا تقم بشيء
        if ($qtyToAdd <= 0) {
            $this->qtyToAdd = null; // تصفير الحقل
            return;
        }

        // 2. إضافة الكمية للمنتج الرئيسي (إذا كان مستخدماً)
        // if (isset($this->quantities['main'])) {
        //     $this->quantities['main'] = (int) ($this->quantities['main'] ?? 0) + $qtyToAdd;
        // }

        // 3. إضافة الكمية لجميع المتغيرات (Variations)
        if (!empty($this->variations)) {
            foreach ($this->variations as $variation) {
                $variationId = $variation['id'];

                // جلب الكمية الحالية المسجلة في $quantities
                $currentQty = (int) ($this->quantities[$variationId] ?? 0);

                // إضافة الكمية الجديدة ($qtyToAdd) إلى الكمية الحالية
                $this->quantities[$variationId] = $currentQty + $qtyToAdd;
            }
        }

        // 4. تصفير حقل "إضافة كمية للكل" بعد الانتهاء
        $this->qtyToAdd = null;
    }

    /**
     * دالة جديدة لحفظ الكميات المحدثة
     */
    public function saveStockQuantities()
    {
        try {
            $updatePayload = [];

            // 1. تجميع تحديثات الكميات للمتغيرات
            foreach ($this->quantities as $variationId => $quantity) {
                // 'main' هو مفتاح خاص بالمنتج الرئيسي (إذا استخدمته)
                // نحن نهتم فقط بالمتغيرات التي لها ID رقمي
                if (is_numeric($variationId)) {
                    $updatePayload[] = [
                        'id' => (int) $variationId,
                        'stock_quantity' => (int) $quantity
                    ];
                }
            }

            // 2. تحديث كمية المنتج الرئيسي (إذا كان مستخدماً)
            // if (isset($this->quantities['main']) && $this->product) {
            //     $this->wooService->updateProductStock($this->product['id'], $this->quantities['main']);
            // }

            // 3. إرسال التحديثات دفعة واحدة للمتغيرات
            if (!empty($updatePayload) && $this->product) {
                // نستخدم ID المنتج الرئيسي لتحديث متغيراته
                $this->wooService->batchUpdateVariations($this->product['id'], ['update' => $updatePayload]);
            }

            // 4. ✨ *** الخطوة الجديدة: تحديث جدول المخزون المحلي ***
            $storeId = 1 ?? null; // <-- !!! افترض أن store_id موجود في بيانات المستخدم
            $userId = Auth::id();

            if (!$storeId) {
                logger()->error('Inventory Sync Error: store_id is missing for user.', ['user_id' => $userId]);
                Toaster::error('حدث خطأ في مزامنة المخزون: لم يتم العثور على معرّف المتجر.');
            } else {

                //
                // بما أن جدولك هو "log" لتسجيل الحركات
                // سنقوم بحساب الفرق وإضافة سجل جديد
                //
                foreach ($this->quantities as $variationId => $newQty) {
                    // نتأكد أنه ID رقمي (لمنتج فرعي)
                    if (is_numeric($variationId)) {

                        // جلب الكمية القديمة التي خزنّاها
                        $oldQty = (int) ($this->originalQuantities[$variationId] ?? 0);
                        $newQty = (int) $newQty;

                        // حساب الفرق
                        $difference = $newQty - $oldQty;

                        // إذا كان هناك فرق، قم بتسجيله
                        if ($difference != 0) {

                            // *** بداية المنطق الجديد ***
                            $inventoryType = null;
                            $logQuantity = 0;

                            if ($difference > 0) {
                                // الكمية "داخلة" - أضفنا مخزون
                                $inventoryType = InventoryType::INPUT;
                                $logQuantity = $difference; // الكمية المضافة
                            } else {
                                // الكمية "طالعة" - سحبنا مخزون
                                $inventoryType = InventoryType::OUTPUT;
                                $logQuantity = abs($difference); // نسجل القيمة الموجبة للكمية الخارجة
                            }

                            try {
                                // $variationId هو المعرف من ووكومرس (remote_wp_id)
                                // ابحث عن المنتج المحلي باستخدام معرّف ووكومرس
                                $localProduct = Product::where('remote_wp_id', (int) $variationId)->first();

                                if ($localProduct) {
                                    // 1. قم بتحديث الكمية في جدول products المحلي (هذه الخطوة مهمة جداً)
                                    $localProduct->stock_quantity = (int) $newQty;
                                    $localProduct->save();

                                    // 2. استخدم الـ ID المحلي للتسجيل في المخزون
                                    Inventory::create([
                                        'product_id' => $localProduct->id, // <-- تم التصحيح: استخدام ID المحلي
                                        'store_id'   => (int) $storeId,
                                        'user_id'    => (int) $userId,
                                        'quantity'   => $logQuantity, // الكمية (دائماً موجبة)
                                        'type'       => $inventoryType, // النوع (INPUT أو OUTPUT)
                                    ]);

                                } else {
                                    // (اختياري) تسجيل ملاحظة إذا لم يتم العثور على المنتج المحلي
                                    logger()->warning('Local product not found for sync. Inventory log skipped.', [
                                        'remote_wp_id' => $variationId,
                                        'new_stock' => $newQty
                                    ]);
                                }
                            } catch (\Exception $e) {
                                // (اختياري) تسجيل أي خطأ يحدث أثناء مزامنة الجدول المحلي
                                logger()->error('Error syncing local products table or creating inventory log.', [
                                    'remote_wp_id' => $variationId,
                                    'error' => $e->getMessage()
                                ]);
                                // لا نوقف العملية كلها، فقط نسجل الخطأ
                            }
                        }
                    }
                }
            }
            // *** نهاية الخطوة الجديدة ***


            Toaster::success('🎉 تم حفظ الكميات في ووكومرس والمخزون المحلي بنجاح!');
            $this->modal('stock-qty-product-modal')->close();

        } catch (\Exception $e) {
            logger()->error('Error saving stock quantities', ['error' => $e->getMessage()]);
            Toaster::error('حدث خطأ فادح أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function printBarcodes()
    {
        $pdf = Pdf::loadView('livewire.pages.product.pdf.index', [
            'product' => $this->product,
            'variations' => $this->variations,
            'quantities' => $this->quantities,
        ], [], [
            'format' => [60, 40]
        ]);

        dd($this->product);

        return response()->streamDownload(function () use ($pdf) {
            $pdf->stream();
        }, 'barcode.pdf');
    }

    #[Computed]
    public function getMrbpRole($productId)
    {
        $result = $this->wooService->getMrbpRoleById($productId);
        return $result;
    }

    public function deleteProduct($productId)
    {
        $this->wooService->deleteProductById($productId);
    }

    public function updateProductFeatured($productId, $featured)
    {
        $this->wooService->updateProductFeatured($productId, $featured);
        Toaster::success('تم تحديث المنتج بنجاح');
    }

    public function syncProduct()
    {
//        $subId = optional(Auth::user())->subscription_id;
//        abort_unless($subId, 403, 'No subscription assigned to the current user.');
//
//
        SyncProduct::dispatch((int) Auth::id());
    }

    public function openListVariationsModal($productId)
    {
        try {
            $product = $this->wooService->getProduct($productId);
            $this->productData = $product;
            $this->main_price = $product['regular_price'];
            $this->main_sale_price = $product['sale_price'];

            $metaData = $product['meta_data'] ?? [];
            $this->showVariationTable = false;
            foreach ($metaData as $meta) {
                if ($meta['key'] == 'mrbp_metabox_user_role_enable') {
                    $this->showVariationTable = ($meta['value'] == 'yes');
                }
            }

            // تهيئة قيم الأدوار للمنتج الأب
            $this->parentRoleValues = [];
            $roles = $this->wooService->getRoles();
            foreach ($roles as $role) {
                if (isset($role['role'])) {
                    $this->parentRoleValues[$role['role']] = ''; // تهيئة بفارغ
                }
            }

            // استخراج أسعار الأدوار المحفوظة (مع فلتر للبيانات التالفة)
            foreach ($metaData as $meta) {
                if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                    foreach ($meta['value'] as $roleEntry) {
                        if (!is_array($roleEntry)) continue;
                        $roleKey = array_key_first($roleEntry);

                        // ✨ فلتر ذكي لتجاهل أي بيانات محفوظة بشكل خاطئ
                        if ($roleKey && !in_array(strtolower($roleKey), ['id', 'name'])) {
                            $priceValue = $roleEntry['mrbp_regular_price'] ?? null;
                            if ($priceValue !== null) {
                                $this->parentRoleValues[$roleKey] = $priceValue;
                            }
                        }
                    }
                }
            }

            $variations = $this->wooService->getProductVariationsWithRoles($productId);
            $this->productVariations = $variations;
            $this->variationValues = [];
            $this->price = [];

            foreach ($variations as $variationIndex => $variation) {
                $this->price[$variationIndex] = $variation['regular_price'];
                $this->variationValues[$variationIndex] = $variation['role_values'] ?? [];
            }

            $this->modal('list-variations')->show();
        } catch (\Exception $e) {
            logger()->error('Error opening variations modal', ['error' => $e->getMessage()]);
            Toaster::error('حدث خطأ أثناء جلب البيانات: ' . $e->getMessage());
        }
    }

    #[Computed()]
    public function getRoles()
    {
        $roles = $this->wooService->getRoles();
        $this->roles = $roles;
        return $roles;
    }

    public function updateVariationMrbpRole($variationId, $roleKey, $value)
    {
        $this->wooService->updateVariationMrbpRole($variationId, $roleKey, $value);
        Toaster::success('تم تحديث المنتج بنجاح');
    }

    public function updatePrice($value, $key)
    {
        try {
            $this->wooService->updateProductVariation($this->productData['id'], $value, [
                'price' => $key,
                'regular_price' => $key
            ]);
            Toaster::success('تم تحديث المنتج بنجاح');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'woocommerce_rest_invalid_product_id')) {
                Toaster::error('المنتج غير موجود أو تم حذفه.');
            } else {
                throw $e; // غير هيك ارمي الخطأ
            }
        }
    }


    /**
     * تحديث سعر الدور للمنتج الأساسي
     */
    public function updateProductMrbpRole($roleKey, $value)
    {
        // dd($this->productData);
        try {
            // التحقق من أن معرف المنتج صالح
            if (empty($this->productData['id']) || $this->productData['id'] == 0) {
                // استخدام معرف المنتج من productData إذا كان متاحًا
                if (isset($this->productData['id']) && !empty($this->productData['id'])) {
                    logger()->info('Using product ID from productData', ['productId' => $this->productData['id']]);
                } else {
                    logger()->error('Invalid product ID and no productData available', ['providedId' => $this->productData['id']]);
                    Toaster::error('معرف المنتج غير صالح.');
                    return;
                }
            }

            // تسجيل المعلومات قبل تحديث سعر الدور
            // logger()->info('Updating product role price', [
            //     'productId' => $productId,
            //     'roleKey' => $roleKey,
            //     'value' => $value
            // ]);

            // تحديث سعر الدور للمنتج
            $result = $this->wooService->updateProductRolePrice($this->productData['id'], $roleKey, $value);

            // تحديث القيمة في مصفوفة parentRoleValues
            $this->parentRoleValues[$roleKey] = $value;

            // تسجيل نتيجة التحديث
            logger()->info('Product role price update result', [
                'productId' => $this->productData['id'],
                'roleKey' => $roleKey,
                'success' => $result !== false
            ]);

            // عرض رسالة نجاح للمستخدم
            Toaster::success('تم تحديث سعر الدور بنجاح.');
        } catch (\Exception $e) {
            // تسجيل الخطأ وعرض رسالة للمستخدم
            // logger()->error('Error updating product role price', [
            //     'productId' => $productId,
            //     'roleKey' => $roleKey,
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            Toaster::error('حدث خطأ أثناء تحديث سعر الدور: ' . $e->getMessage());
        }
    }

    public function setAllPricesForRole($roleKey)
    {
        $value = $this->columnPrices[$roleKey] ?? null;

        if (!is_numeric($value)) {
            Toaster::warning('الرجاء إدخال سعر رقمي صالح.');
            return;
        }

        // تحديث أسعار المنتج الأب في الذاكرة
        $this->parentRoleValues[$roleKey] = $value;

        // ✅ إذا كان الدور هو Customer → حدث السعر الرئيسي
        if ($roleKey === 'customer') {
            $this->main_price = $value;
            $this->main_sale_price = $value;
        }

        // تحديث قيم المتغيرات في الذاكرة فقط
        foreach ($this->productVariations as $index => $variation) {
            $this->variationValues[$index][$roleKey] = $value;
        }

        Toaster::info('تم تطبيق السعر مؤقتاً. اضغط "حفظ كل التغييرات" لتأكيد.');
    }


    public function updateMainProductPrice()
    {
        $this->wooService->updateMainProductPrice($this->productData['id'], $this->main_price);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    // Index.php

// ✨ الدالة الأساسية الجديدة لحفظ كل التغييرات دفعة واحدة
    public function saveAllChanges()
    {
        try {
            // --- ✨ 1. تحديد سعر العميل أولاً ---

            // !!! هام جداً: تأكد أن المفتاح هنا صحيح 100%
            // يجب أن يتطابق مع الـ 'role' من دالة getRoles()
            $customerRoleKey = 'customer';
            $customerPrice = null;

            // التحقق إذا كان سعر الـ Customer (للمنتج الأب) موجوداً وهو رقم صالح
            if (isset($this->parentRoleValues[$customerRoleKey]) && is_numeric($this->parentRoleValues[$customerRoleKey])) {
                $customerPrice = $this->parentRoleValues[$customerRoleKey];
            }

            // --- ✨ 2. تجميع تحديثات المتغيرات ---
            $updatePayload = [];

            foreach ($this->productVariations as $index => $variation) {
                $variationId = $variation['id'];
                $metaData = $variation['meta_data'] ?? [];

                // جلب مصفوفة أسعار الأدوار الحالية للمتغير
                $newRoleValuesForVariation = $this->variationValues[$index] ?? [];

                // جلب السعر الأساسي الحالي للمتغير
                $currentRegularPrice = $this->price[$index] ?? $variation['regular_price'];


                // --- ✨ 3. تطبيق سعر العميل على المتغيرات (إذا تم تحديده) ---
                if ($customerPrice !== null) {
                    // 3.أ: "دهس" السعر الأساسي للمتغير بسعر العميل
                    $currentRegularPrice = $customerPrice;

                    // 3.ب: "دهس" سعر "Customer" للمتغير بسعر العميل
                    $newRoleValuesForVariation[$customerRoleKey] = $customerPrice;
                }


                // --- 4. إعادة بناء مصفوفة الميتا (meta_data) للمتغير ---
                $mrbpRoleFound = false;
                foreach ($metaData as &$meta) {
                    if ($meta['key'] === 'mrbp_role') {
                        $mrbpRoleFound = true;
                        $updatedRoles = [];
                        // نستخدم $newRoleValuesForVariation التي قد تحتوي الآن على سعر العميل المحدث
                        foreach ($newRoleValuesForVariation as $roleKey => $price) {
                            if (is_numeric($price) && $price !== '') {
                                $updatedRoles[] = [
                                    $roleKey => ucfirst($roleKey),
                                    'mrbp_regular_price' => $price, 'mrbp_sale_price' => '', 'mrbp_make_empty_price' => ""
                                ];
                            }
                        }
                        $meta['value'] = $updatedRoles;
                        break;
                    }
                }
                // (نفس المنطق لإضافة 'mrbp_role' إذا لم يكن موجوداً)
                if (!$mrbpRoleFound) {
                    $updatedRoles = [];
                    foreach ($newRoleValuesForVariation as $roleKey => $price) {
                        if (is_numeric($price) && $price !== '') {
                            $updatedRoles[] = [
                                $roleKey => ucfirst($roleKey),
                                'mrbp_regular_price' => $price, 'mrbp_sale_price' => '', 'mrbp_make_empty_price' => ""
                            ];
                        }
                    }
                    if (!empty($updatedRoles)) {
                        $metaData[] = ['key' => 'mrbp_role', 'value' => $updatedRoles];
                    }
                }
                // --- نهاية بناء الميتا ---

                // إضافة التحديث للمصفوفة النهائية
                $updatePayload[] = [
                    'id' => $variationId,
                    'regular_price' => $currentRegularPrice, // <-- استخدام السعر المحدث
                    'meta_data' => $metaData
                ];
            }

            // إرسال تحديثات المتغيرات دفعة واحدة
            if (!empty($updatePayload)) {
                $this->wooService->batchUpdateVariations($this->productData['id'], ['update' => $updatePayload]);
            }

            // --- ✨ 5. تحديث بيانات المنتج الرئيسي ---
            if ($customerPrice !== null) {
                // "ندهس" القيم الحالية في الفورم بقيمة العميل
                $this->main_price = $customerPrice;
                $this->main_sale_price = $customerPrice;
            }

            // الآن، سيتم استدعاء هذه الدوال بالقيم المحدثة
            $this->wooService->updateMainProductPrice($this->productData['id'], $this->main_price);
            $this->wooService->updateMainSalePrice($this->productData['id'], $this->main_sale_price);

            // حفظ جميع أسعار الأدوار للمنتج الرئيسي (بما فيها سعر العميل)
            foreach($this->parentRoleValues as $roleKey => $value) {
                $this->wooService->updateProductRolePrice($this->productData['id'], $roleKey, $value);
            }

            Toaster::success('🎉 تم حفظ جميع التغييرات بنجاح!');
            $this->modal('list-variations')->close();

        } catch (\Exception $e) {
            logger()->error('Error saving all variation changes', ['error' => $e->getMessage()]);
            Toaster::error('حدث خطأ فادح أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function updateMainSalePrice()
    {
        $this->wooService->updateMainSalePrice($this->productData['id'], $this->main_sale_price);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    public function updateMrbpMetaboxUserRoleEnable()
    {
        $yes = $this->showVariationTable ? 'yes' : 'no';
        $this->wooService->updateMrbpMetaboxUserRoleEnable($this->productData['id'], $yes);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    public function updateProductStatus($productId, $status)
    {
        $status = $status == 'publish' ? 'publish' : 'draft';

        // 1. غير حالة المنتج الأساسي
        $this->wooService->updateProductStatus($productId, $status);

        // 2. جيب الترجمات المرتبطة
        // $translations = $this->wooService->getProductTranslations($productId);

        // if (!empty($translations)) {
        //     foreach ($translations as $lang => $translatedProductId) {
        //         if ($translatedProductId != $productId) { // تأكد أنه مش هو نفس المنتج
        //             $this->wooService->updateProductStatus($translatedProductId, $status);
        //         }
        //     }
        // }

        Toaster::success('تم تحديث حالة المنتج وجميع الترجمات بنجاح');
    }

    public function render()
    {
        $query = [
            'per_page' => $this->perPage,
            'page' => $this->page,
            'status' => 'any', // يبحث في كل الحالات (منشور، مسودة، ..)
            'lang' => app()->getLocale(), // لجلب المنتجات باللغة الحالية
        ];

        // إذا كان هناك نص في مربع البحث
        if (!empty(trim($this->search))) {
            // نستخدم معامل 'search' الذي يوفره ووكومرس للبحث السريع في اسم المنتج وغيره
            $query['search'] = trim($this->search);
        }
        // إذا لم يكن هناك بحث، ولكن تم اختيار تصنيف معين
        elseif ($this->categoryId) {
            $query['category'] = $this->categoryId;
        }

        // نقوم بإرسال طلب واحد فقط وسريع للـ API
        $response = $this->wooService->getProducts($query);

        $collection = collect($response['data'] ?? $response);
        $total = $response['total'] ?? $collection->count();

        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $collection,
            $total,
            $this->perPage,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.pages.product.index', [
            'products' => $products,
        ]);
    }
    /**
     * البحث في متغيرات المنتجات وإرجاع المنتج الأب
     */
    private function searchInVariations(string $searchTerm): ?array
    {
        try {
            // جلب المنتجات المتغيرة
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'per_page' => 50,
                'status' => 'any'
            ]);

            $products = $variableProducts['data'] ?? $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations'])) {
                    // البحث في متغيرات هذا المنتج
                    $variations = $this->wooService->getProductVariations($product['id']);

                    foreach ($variations as $variation) {
                        // فحص SKU للمتغير
                        if (!empty($variation['sku']) && strcasecmp($variation['sku'], $searchTerm) === 0) {
                            return $product; // إرجاع المنتج الأب
                        }

                        // فحص ID للمتغير
                        if (is_numeric($searchTerm) && $variation['id'] == (int)$searchTerm) {
                            return $product;
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error searching in variations', [
                'searchTerm' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

