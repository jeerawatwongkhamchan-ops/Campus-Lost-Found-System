<?php

declare(strict_types=1);

/** @var string $action */
/** @var string $submitLabel */
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var list<array{category_id: int, category_name: string}> $categories */

$category_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา / นาฬิกา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];
?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="mt-6 space-y-5">
    <input type="hidden" name="csrf_token" value="<?= e(reports_csrf_token()) ?>">

    <?php if ($errors !== []): ?>
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <span class="font-semibold">โปรดตรวจสอบข้อมูล:</span> กรุณากรอกข้อมูลในช่องที่ไฮไลต์สีแดงให้ถูกต้องครบถ้วน
        </div>
    <?php endif; ?>

    <!-- ประเภทรายงาน -->
    <fieldset>
        <legend class="block text-sm font-semibold text-gray-700">ประเภทรายการ <span class="text-red-500">*</span></legend>
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-center gap-3 rounded-lg border p-3.5 cursor-pointer transition <?= $values['report_type'] === 'lost' ? 'border-amber-400 bg-amber-50/50' : 'border-gray-200 hover:bg-gray-50' ?>">
                <input type="radio" name="report_type" value="lost" <?= $values['report_type'] === 'lost' ? 'checked' : '' ?> class="text-amber-600 focus:ring-amber-500">
                <div>
                    <span class="text-sm font-semibold text-gray-900">ของหาย (ฉันทำของหาย)</span>
                    <p class="text-xs text-gray-500">ต้องการความช่วยเหลือตามหาสิ่งของที่ลืมไว้</p>
                </div>
            </label>
            <label class="flex items-center gap-3 rounded-lg border p-3.5 cursor-pointer transition <?= $values['report_type'] === 'found' ? 'border-emerald-400 bg-emerald-50/50' : 'border-gray-200 hover:bg-gray-50' ?>">
                <input type="radio" name="report_type" value="found" <?= $values['report_type'] === 'found' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500">
                <div>
                    <span class="text-sm font-semibold text-gray-900">พบสิ่งของ (ฉันเก็บของได้)</span>
                    <p class="text-xs text-gray-500">เก็บสิ่งของได้และต้องการส่งมอบคืนเจ้าของ</p>
                </div>
            </label>
        </div>
        <?php if (isset($errors['report_type'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['report_type']) ?></p>
        <?php endif; ?>
    </fieldset>

    <!-- ชื่อสิ่งของ -->
    <div>
        <label for="title" class="block text-sm font-semibold text-gray-700">
            ชื่อสิ่งของ <span class="text-red-500">*</span>
        </label>
        <input required minlength="3" maxlength="255" type="text" id="title" name="title" value="<?= e($values['title']) ?>" class="mt-1 block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900" placeholder="เช่น หูฟังไร้สาย AirPods Pro เคสสีฟ้า, กระเป๋าสตางค์สีดำ">
        <?php if (isset($errors['title'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['title']) ?></p>
        <?php endif; ?>
    </div>

    <!-- หมวดหมู่ -->
    <div>
        <label for="category_id" class="block text-sm font-semibold text-gray-700">
            หมวดหมู่สิ่งของ <span class="text-red-500">*</span>
        </label>
        <select required id="category_id" name="category_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
            <option value="">-- กรุณาเลือกหมวดหมู่สิ่งของ --</option>
            <?php foreach ($categories as $category): ?>
                <?php 
                    $cat_name = $category['category_name'];
                    $th_name = $category_translations[$cat_name] ?? $cat_name;
                ?>
                <option value="<?= (int) $category['category_id'] ?>" <?= $values['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>>
                    <?= e($th_name) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['category_id']) ?></p>
        <?php endif; ?>
    </div>

    <!-- สถานที่ -->
    <div>
        <label for="location" class="block text-sm font-semibold text-gray-700">
            สถานที่พบ หรือจุดที่คาดว่าทำหาย <span class="text-red-500">*</span>
        </label>
        <input required minlength="3" maxlength="255" type="text" id="location" name="location" list="campus-locations" value="<?= e($values['location']) ?>" class="mt-1 block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900" placeholder="เลือกหรือพิมพ์ เช่น บริเวณโต๊ะหน้าห้องสมุดชั้น 2, โรงอาหารกลาง">
        <datalist id="campus-locations">
            <option value="อาคารเรียนรวม (Lecture Hall)"></option>
            <option value="สำนักหอสมุดกลาง (Central Library)"></option>
            <option value="ศูนย์อาหารกลาง / โรงอาหาร (Food Center)"></option>
            <option value="อาคารวิทยาการสารสนเทศ / คอมพิวเตอร์ (IT & Computer Science)"></option>
            <option value="อาคาร SC.01-09 (Science Complex)"></option>
            <option value="ศูนย์กีฬาและโรงยิมเนเซียม (Sports Complex)"></option>
            <option value="อาคารกิจกรรมนักศึกษา (Student Union)"></option>
            <option value="ลานจอดรถหน้าอาคารเรียน (Parking Area)"></option>
            <option value="ป้ายรถโดยสารภายในวิทยาเขต (Campus Bus Stop)"></option>
        </datalist>
        <?php if (isset($errors['location'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['location']) ?></p>
        <?php endif; ?>
    </div>

    <!-- วันและเวลา -->
    <div>
        <label for="event_datetime" class="block text-sm font-semibold text-gray-700">
            วันและเวลาที่เกิดเหตุ <span class="text-red-500">*</span>
        </label>
        <input required type="datetime-local" id="event_datetime" name="event_datetime" value="<?= e($values['event_datetime']) ?>" class="mt-1 block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
        <?php if (isset($errors['event_datetime'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['event_datetime']) ?></p>
        <?php endif; ?>
    </div>

    <!-- รายละเอียด -->
    <div>
        <label for="description" class="block text-sm font-semibold text-gray-700">
            รายละเอียดและจุดสังเกตทั่วไป <span class="text-red-500">*</span>
        </label>
        <textarea required minlength="10" maxlength="5000" id="description" name="description" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm transition focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900" placeholder="ระบุลักษณะทั่วไป เช่น สี ยี่ห้อ หรือจุดสังเกตภายนอกเพื่อให้จำได้ง่าย (อย่างน้อย 10 ตัวอักษร)"><?= e($values['description']) ?></textarea>
        <?php if (isset($errors['description'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['description']) ?></p>
        <?php endif; ?>
    </div>

    <!-- ข้อมูลลับเฉพาะ (Private Note - Security / Ownership Challenge) -->
    <div class="rounded-xl border border-amber-200/80 bg-amber-50/60 p-4">
        <label for="private_note" class="block text-sm font-semibold text-gray-900">
            ข้อมูลลับเฉพาะสำหรับยืนยันความเป็นเจ้าของ (Private Note)
            <span class="text-xs font-normal text-amber-800 ml-1.5">(แสดงเฉพาะคุณและเจ้าหน้าที่)</span>
        </label>
        <p class="mt-1 text-xs text-gray-600">
            ใช้บันทึกจุดสังเกตเฉพาะที่ต้องการซ่อนไว้จากบุคคลทั่วไป เพื่อใช้ทดสอบถามผู้ที่มาขอรับคืน เช่น ตำหนิลับ, สิ่งของในกระเป๋า, หรือรหัสซีเรียล
        </p>
        <textarea maxlength="2000" id="private_note" name="private_note" rows="2" class="mt-2 block w-full rounded-lg border border-amber-300 bg-white px-3.5 py-2 text-sm shadow-xs transition focus:border-amber-600 focus:outline-none focus:ring-1 focus:ring-amber-600" placeholder="เช่น ด้านในกระเป๋ามีเงินสด 260 บาท และมีพวงกุญแจโดราเอมอน"><?= e($values['private_note'] ?? '') ?></textarea>
        <?php if (isset($errors['private_note'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= e($errors['private_note']) ?></p>
        <?php endif; ?>
    </div>

    <!-- แนบรูปภาพสิ่งของ -->
    <div>
        <label class="block text-sm font-semibold text-gray-700">
            รูปภาพสิ่งของ (ถ้ามี)
        </label>
        <p class="mt-0.5 text-xs text-gray-500">
            แนบรูปภาพสิ่งของเพื่อช่วยให้เจ้าของหรือผู้พบเห็นสามารถระบุสิ่งของได้ง่ายขึ้น (รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 3 MB)
        </p>

        <?php if (!empty($values['image_filename'])): ?>
            <?php $current_img_url = get_report_image_url($values['image_filename']); ?>
            <?php if ($current_img_url !== null): ?>
                <div class="mt-3 flex items-start gap-4 rounded-xl border border-gray-200 bg-gray-50/70 p-3.5">
                    <img src="<?= e($current_img_url) ?>" alt="รูปภาพปัจจุบัน" class="h-20 w-20 rounded-lg object-cover border border-gray-200 shadow-2xs">
                    <div class="flex-1 text-xs">
                        <span class="font-medium text-gray-900">รูปภาพปัจจุบันของรายงานนี้</span>
                        <p class="mt-0.5 text-gray-500">หากต้องการเปลี่ยน ให้เลือกไฟล์ใหม่ด้านล่าง หรือเลือกติ๊กถูกเพื่อลบรูปเดิม</p>
                        <label class="mt-2.5 inline-flex items-center gap-2 cursor-pointer font-medium text-rose-600 hover:text-rose-700">
                            <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                            ลบรูปภาพนี้ออกจากรายงาน
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="mt-2">
            <div id="image-drop-area" class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/40 p-5 text-center transition hover:border-gray-400 hover:bg-gray-50">
                <input
                    type="file"
                    id="image"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    class="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                    onchange="previewReportImage(this)"
                >
                <div id="upload-prompt" class="space-y-1">
                    <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <div class="text-xs text-gray-600">
                        <span class="font-semibold text-gray-900 hover:underline">คลิกเลือกรูปภาพ</span> หรือลากไฟล์มาวางที่นี่
                    </div>
                    <p class="text-[11px] text-gray-400">JPG, PNG หรือ WEBP สูงสุด 3 MB</p>
                </div>

                <div id="image-preview-container" class="hidden mt-1 flex flex-col items-center gap-2">
                    <img id="image-preview" src="#" alt="ตัวอย่างรูปภาพ" class="max-h-48 rounded-lg object-contain border border-gray-200 shadow-xs">
                    <div class="flex items-center gap-2 text-xs">
                        <span id="preview-filename" class="font-medium text-gray-700"></span>
                        <button type="button" onclick="clearSelectedImage()" class="text-rose-600 hover:text-rose-800 font-medium underline">
                            ยกเลิกไฟล์นี้
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($errors['image'])): ?>
            <p class="mt-1.5 text-xs text-red-600"><?= e($errors['image']) ?></p>
        <?php endif; ?>
    </div>

    <script>
        function previewReportImage(input) {
            const previewContainer = document.getElementById('image-preview-container');
            const previewImage = document.getElementById('image-preview');
            const uploadPrompt = document.getElementById('upload-prompt');
            const filenameLabel = document.getElementById('preview-filename');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 3145728) {
                    alert('ขนาดไฟล์เกิน 3 MB กรุณาเลือกไฟล์ที่มีขนาดเล็กลง');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    filenameLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                    previewContainer.classList.remove('hidden');
                    uploadPrompt.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function clearSelectedImage() {
            const input = document.getElementById('image');
            const previewContainer = document.getElementById('image-preview-container');
            const previewImage = document.getElementById('image-preview');
            const uploadPrompt = document.getElementById('upload-prompt');
            input.value = '';
            previewImage.src = '#';
            previewContainer.classList.add('hidden');
            uploadPrompt.classList.remove('hidden');
        }
    </script>

    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
        <a href="/reports/index.php" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
            ยกเลิก
        </a>
        <button type="submit" class="rounded-lg bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">
            <?= e($submitLabel) ?>
        </button>
    </div>
</form>
