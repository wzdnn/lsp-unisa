<?php

namespace Database\Seeders;

use App\Models\AssessmentForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MukK3Tlm2026AssessmentSeeder extends Seeder
{
    private const VERSION = 2026081002;
    private const SCHEME = 'Petugas Keselamatan dan Kesehatan Kerja';
    private const SCHEME_NUMBER = '001/FST/LSP.UNISAYogya/2024';
    private array $unitIds = [];
    private array $elementIds = [];
    private array $criteriaIds = [];
    private ?int $schemeId = null;
    private array $programIds = [];

    public function run(): void
    {
        DB::transaction(function () {
            $this->prepareMasterRelations();
            foreach ($this->forms() as $definition) {
                $this->seedForm($definition);
            }
        });
    }

    private function seedForm(array $definition): void
    {
        $sections = $definition['sections'];
        unset($definition['sections']);

        $definition['kdlsp_skema'] = $this->schemeId;
        $form = AssessmentForm::updateOrCreate(
            ['code' => $definition['code']],
            $definition
        );
        if (Schema::hasTable('lsp_assessment_form_prodi')) {
            $form->programs()->sync($this->programIds);
        }

        $version = $form->versions()->firstOrCreate(
            ['version' => self::VERSION],
            [
                'status' => 'published',
                'settings' => [
                    'source' => 'MASTER MUK-K3 TLM-2026.pdf',
                    'scheme_name' => self::SCHEME,
                    'scheme_number' => self::SCHEME_NUMBER,
                    'master_year' => 2026,
                ],
                'published_at' => now(),
            ]
        );

        if (!$version->wasRecentlyCreated) {
            return;
        }

        $form->versions()->whereKeyNot($version->id)->where('status', 'published')->update(['status' => 'archived']);
        foreach ($sections as $sectionOrder => $section) {
            $questions = $section['questions'];
            unset($section['questions']);
            $sectionModel = $version->sections()->create($section + ['sort_order' => $sectionOrder]);
            foreach ($questions as $questionOrder => $question) {
                $questionModel = $sectionModel->questions()->create($this->questionAttributes($question) + ['sort_order' => $questionOrder]);
                $this->syncQuestionUnits($questionModel, $question);
            }
        }
    }

    private function prepareMasterRelations(): void
    {
        if (!Schema::hasTable('lsp_skema') || !Schema::hasTable('lsp_skema_unitkompetensi')) {
            return;
        }

        $scheme = DB::table('lsp_skema')->where('no_skema', self::SCHEME_NUMBER)
            ->orWhere('skema', 'like', '%Petugas K3%')
            ->orWhere('skema', 'like', '%Petugas Keselamatan dan Kesehatan Kerja%')
            ->first();
        if (!$scheme) {
            $this->schemeId = DB::table('lsp_skema')->insertGetId([
                'skema' => self::SCHEME,
                'no_skema' => self::SCHEME_NUMBER,
                'isActive' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'kdlsp_skema');
        } else {
            $this->schemeId = (int) $scheme->kdlsp_skema;
        }

        if (Schema::hasTable('pt_unitkerja')) {
            $this->programIds = DB::table('pt_unitkerja')
                ->where('unitkerja', 'like', '%Teknologi Laboratorium Medis%')
                ->orWhere('unitkerjapendek', 'like', '%TLM%')
                ->pluck('kdunitkerja')->map(fn ($id) => (int) $id)->all();
        }

        foreach ($this->competencyUnits() as $index => $unit) {
            $standard = $index < 2 ? 'SKKNI No. 309 Tahun 2017' : 'SKKNI No. 38 Tahun 2019';
            DB::table('lsp_skema_unitkompetensi')->updateOrInsert(
                ['kdlsp_skema' => $this->schemeId, 'kode_unit' => $unit['code']],
                ['judul_unit' => $unit['title'], 'standar_kompetensi_kerja' => $standard, 'updated_at' => now(), 'created_at' => now()]
            );
            $unitId = (int) DB::table('lsp_skema_unitkompetensi')
                ->where('kdlsp_skema', $this->schemeId)->where('kode_unit', $unit['code'])
                ->value('kdlsp_skema_unitkompetensi');
            $this->unitIds[$unit['code']] = $unitId;

            if (!Schema::hasTable('lsp_skema_unitkompetensi_elemen') || !Schema::hasTable('lsp_skema_unitkompetensi_elemen_kriteria')) {
                continue;
            }
            foreach ($unit['elements'] as $element) {
                DB::table('lsp_skema_unitkompetensi_elemen')->updateOrInsert(
                    ['kdlsp_skema_unitkompetensi' => $unitId, 'elemen' => $element['code'].' '.$element['name']],
                    ['updated_at' => now(), 'created_at' => now()]
                );
                $elementId = (int) DB::table('lsp_skema_unitkompetensi_elemen')
                    ->where('kdlsp_skema_unitkompetensi', $unitId)->where('elemen', $element['code'].' '.$element['name'])
                    ->value('kdlsp_skema_unitkompetensi_elemen');
                $elementKey = $unit['code'].'|'.$element['code'];
                $this->elementIds[$elementKey] = $elementId;

                foreach ($element['criteria'] as $kukCode => $criterion) {
                    DB::table('lsp_skema_unitkompetensi_elemen_kriteria')->updateOrInsert(
                        ['kdlsp_skema_unitkompetensi_elemen' => $elementId, 'kriteria' => $kukCode.' '.$criterion],
                        ['updated_at' => now(), 'created_at' => now()]
                    );
                    $this->criteriaIds[$elementKey.'|'.$kukCode] = (int) DB::table('lsp_skema_unitkompetensi_elemen_kriteria')
                        ->where('kdlsp_skema_unitkompetensi_elemen', $elementId)->where('kriteria', $kukCode.' '.$criterion)
                        ->value('kdlsp_skema_unitkompetensi_elemen_kriteria');
                }
            }
        }
    }

    private function questionAttributes(array $question): array
    {
        $settings = $question['settings'] ?? [];
        $unitCode = $settings['unit_code'] ?? null;
        $elementCode = $settings['element_code'] ?? null;
        $kukCode = $settings['kuk_code'] ?? null;
        if ($unitCode && isset($this->unitIds[$unitCode])) {
            $question['kdlsp_skema_unitkompetensi'] = $this->unitIds[$unitCode];
            if ($elementCode) {
                $elementKey = $unitCode.'|'.$elementCode;
                $question['kdlsp_skema_unitkompetensi_elemen'] = $this->elementIds[$elementKey] ?? null;
                $question['kdlsp_skema_unitkompetensi_elemen_kriteria'] = $kukCode ? ($this->criteriaIds[$elementKey.'|'.$kukCode] ?? null) : null;
            }
        }
        return $question;
    }

    private function syncQuestionUnits($questionModel, array $question): void
    {
        if (!Schema::hasTable('lsp_assessment_question_units')) {
            return;
        }
        $settings = $question['settings'] ?? [];
        $codes = $settings['unit_codes'] ?? (isset($settings['unit_code']) ? [$settings['unit_code']] : []);
        foreach ($codes as $unitCode) {
            if (!isset($this->unitIds[$unitCode])) continue;
            $elementKey = $unitCode.'|'.($settings['element_code'] ?? '');
            DB::table('lsp_assessment_question_units')->updateOrInsert([
                'question_id' => $questionModel->id,
                'kdlsp_skema_unitkompetensi' => $this->unitIds[$unitCode],
                'kdlsp_skema_unitkompetensi_elemen' => $this->elementIds[$elementKey] ?? null,
                'kdlsp_skema_unitkompetensi_elemen_kriteria' => isset($settings['kuk_code']) ? ($this->criteriaIds[$elementKey.'|'.$settings['kuk_code']] ?? null) : null,
            ], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function forms(): array
    {
        return array_merge(
            [$this->documentChecklist(), $this->apl01(), $this->apl02()],
            $this->planningForms(),
            $this->agreementForms(),
            $this->assessmentInstruments(),
            $this->postAssessmentForms(),
            [$this->validationForm()]
        );
    }

    private function documentChecklist(): array
    {
        $documents = [
            'FR.APL.01 Permohonan Sertifikasi Kompetensi', 'FR.APL.02 Asesmen Mandiri', 'Portofolio Asesi',
            'FR.MAPA.01 Merencanakan Aktivitas dan Proses Asesmen', 'Skema Sertifikasi', 'Standar Kompetensi',
            'Peta Kompetensi Pekerjaan', 'FR.MAPA.02 Peta Instrumen Asesmen', 'FR.AK.07 Ceklis Penyesuaian yang Wajar',
            'FR.AK.04 Formulir Banding', 'FR.AK.01 Persetujuan Asesmen dan Kerahasiaan', 'FR.IA.01 Ceklis Observasi',
            'FR.IA.02 Tugas Praktik Demonstrasi', 'FR.IA.03 Pertanyaan Mendukung Observasi',
            'FR.IA.04A Daftar Instruksi Terstruktur', 'FR.IA.04B Penilaian Kegiatan Terstruktur',
            'FR.IA.05A/B/C Pertanyaan, Kunci, dan Lembar Jawaban Pilihan Ganda',
            'FR.IA.06A/B/C Pertanyaan, Kunci, dan Lembar Jawaban Esai', 'FR.IA.07 Daftar Pertanyaan Lisan',
            'FR.IA.08 Ceklis Verifikasi Portofolio', 'FR.IA.09 Pertanyaan Wawancara', 'FR.IA.10 Verifikasi Pihak Ketiga',
            'FR.IA.11 Ceklis Reviu Produk', 'FR.AK.02 Rekaman Asesmen Kompetensi',
            'FR.AK.03 Umpan Balik dan Catatan Asesmen', 'FR.AK.05 Laporan Asesmen',
            'FR.AK.06 Meninjau Proses Asesmen', 'FR.VA Memberikan Kontribusi dalam Validasi Asesmen',
        ];
        $questions = [];
        foreach ($documents as $index => $document) {
            $questions[] = $this->q('CHECKLIST_'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'radio', $document, true, $this->options(['ada' => 'Ada', 'tidak' => 'Tidak ada']));
            $questions[] = $this->q('CHECKLIST_'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'_KETERANGAN', 'short_text', 'Keterangan '.$document.'.');
        }
        return $this->form('MUK.CHECKLIST', 'Daftar Periksa Kelengkapan Dokumen', 'pra_asesmen', 'admin', 'admin', [$this->section('Kelengkapan Dokumen MUK', $questions)]);
    }

    private function apl01(): array
    {
        return $this->form('FR.APL.01', 'Permohonan Sertifikasi Kompetensi', 'pra_asesmen', 'asesi', 'admin', [
            $this->section('Bagian 1A - Data Pribadi', [
                $this->q('APL01_NAMA', 'short_text', 'Nama lengkap.', true), $this->q('APL01_NIK', 'short_text', 'Nomor KTP/NIK/Paspor.', true),
                $this->q('APL01_TEMPAT_LAHIR', 'short_text', 'Tempat lahir.', true), $this->q('APL01_TANGGAL_LAHIR', 'date', 'Tanggal lahir.', true),
                $this->q('APL01_JENIS_KELAMIN', 'radio', 'Jenis kelamin.', true, $this->options(['laki_laki' => 'Laki-laki', 'perempuan' => 'Perempuan'])),
                $this->q('APL01_KEBANGSAAN', 'short_text', 'Kebangsaan.', true), $this->q('APL01_ALAMAT', 'long_text', 'Alamat rumah.', true),
                $this->q('APL01_KODE_POS', 'short_text', 'Kode pos.'), $this->q('APL01_TELP_RUMAH', 'short_text', 'Telepon rumah.'),
                $this->q('APL01_TELP_KANTOR', 'short_text', 'Telepon kantor.'), $this->q('APL01_HP', 'short_text', 'Nomor HP.', true),
                $this->q('APL01_EMAIL', 'short_text', 'E-mail.', true), $this->q('APL01_PENDIDIKAN', 'short_text', 'Kualifikasi pendidikan.', true),
            ]),
            $this->section('Bagian 1B - Pekerjaan Sekarang', [
                $this->q('APL01_INSTITUSI', 'short_text', 'Nama institusi/perusahaan.'), $this->q('APL01_JABATAN', 'short_text', 'Jabatan.'),
                $this->q('APL01_ALAMAT_KANTOR', 'long_text', 'Alamat kantor.'), $this->q('APL01_KODE_POS_KANTOR', 'short_text', 'Kode pos kantor.'),
                $this->q('APL01_TELP_PEKERJAAN', 'short_text', 'Telepon kantor.'), $this->q('APL01_FAX', 'short_text', 'Fax kantor.'),
                $this->q('APL01_EMAIL_PEKERJAAN', 'short_text', 'E-mail pekerjaan.'),
            ]),
            $this->section('Bagian 2 - Data Sertifikasi', [
                $this->q('APL01_TUJUAN', 'radio', 'Tujuan asesmen.', true, $this->options(['sertifikasi' => 'Sertifikasi', 'pkt' => 'Pengakuan Kompetensi Terkini', 'rpl' => 'Rekognisi Pembelajaran Lampau', 'lainnya' => 'Lainnya'])),
                $this->q('APL01_TUJUAN_LAIN', 'short_text', 'Tujuan lainnya (jika dipilih).'),
            ]),
            $this->section('Bagian 3 - Bukti Kelengkapan', [
                $this->q('APL01_KHS', 'file_upload', 'Fotokopi Kartu Hasil Studi semester 1-6.', true),
                $this->q('APL01_MAGANG', 'file_upload', 'Surat keterangan menyelesaikan magang/praktik kerja lapangan terkait keselamatan kerja.'),
                $this->q('APL01_PELATIHAN', 'file_upload', 'Sertifikat pelatihan berbasis kompetensi keselamatan kerja.'),
                $this->q('APL01_KTP', 'file_upload', 'Fotokopi KTP.', true), $this->q('APL01_KTM', 'file_upload', 'Fotokopi KTM.', true),
                $this->q('APL01_FOTO', 'file_upload', 'Pas foto terbaru 3x4 dengan latar merah.', true),
                $this->signature('APL01_TTD_ASESI', 'Tanda tangan pemohon/kandidat.', 'asesi'),
                $this->q('APL01_REKOMENDASI_ADMIN', 'radio', 'Rekomendasi admin LSP.', false, $this->options(['diterima' => 'Diterima', 'tidak_diterima' => 'Tidak diterima'])),
            ]),
        ], 'Representasi lengkap field FR.APL.01 dalam master. Workflow aplikasi tetap menggunakan modul pendaftaran APL.01 khusus.');
    }

    private function apl02(): array
    {
        $units = [
            ['M.71KKK00.001.1', 'Menerapkan Peraturan Perundang-undangan dan Standar dalam Pengujian Keselamatan dan Kesehatan Kerja', [
                ['1', 'Mengidentifikasi pengujian K3 sesuai peraturan perundang-undangan dan standar pengujian K3 yang digunakan di tempat kerja', '1.1 Peraturan dan standar K3 diidentifikasi sebagai dasar perlindungan tenaga kerja. 1.2 Peraturan dan standar pengujian K3 dikelompokkan berdasarkan bidang pengujian K3.'],
                ['2', 'Melaksanakan peraturan perundang-undangan dan standar pengujian K3 di tempat kerja', '2.1 Peraturan dilaksanakan sesuai bidang pengujian K3. 2.2 Standar pengujian dilaksanakan sesuai bidangnya. 2.3 Peraturan dan standar pengujian didokumentasikan.'],
            ]],
            ['M.71KKK00.002.1', 'Melakukan Survei Potensi Bahaya K3', [
                ['1', 'Mengidentifikasi potensi bahaya K3 di tempat kerja', '1.1 Sarana survei disiapkan. 1.2 Potensi bahaya diidentifikasi sesuai alur produksi. 1.3 Sumber bahaya diidentifikasi. 1.4 Tenaga kerja berisiko diidentifikasi. 1.5 Lingkungan berisiko diidentifikasi. 1.6 Pengendalian yang sudah dilakukan diidentifikasi sesuai hirarki.'],
                ['2', 'Menetapkan jenis pengujian K3', '2.1 Sampling ditentukan. 2.2 Pemetaan potensi bahaya dibuat. 2.3 Tenaga kerja terdampak ditentukan. 2.4 Jenis pengujian ditentukan sesuai potensi bahaya.'],
            ]],
            ['M.71KKK01.003.1', 'Melakukan Komunikasi Keselamatan dan Kesehatan Kerja', [
                ['1', 'Merencanakan proses kegiatan komunikasi K3', 'Masalah internal dan eksternal, petugas, sumber, serta akses informasi diidentifikasi.'],
                ['2', 'Melaksanakan proses komunikasi K3', 'Informasi pencegahan dikomunikasikan, kebutuhan eksternal dikonsultasikan, masukan dicatat dan dikonfirmasi, serta bahan dan metode komunikasi dibuat.'],
                ['3', 'Memonitor tindak lanjut hasil komunikasi K3', 'Bahan komunikasi didistribusikan dan status penerapannya dipastikan.'],
                ['4', 'Melaporkan kegiatan komunikasi K3', 'Laporan disusun, disampaikan kepada pihak terkait, dan didokumentasikan.'],
            ]],
            ['M.71KKK01.004.1', 'Mengawasi Penerapan Izin Kerja', [
                ['1', 'Mempersiapkan izin kerja', 'Jenis izin dan prosedur izin kerja diidentifikasi sesuai aktivitas dan ketentuan K3.'],
                ['2', 'Mengawasi penerapan izin kerja di tempat kerja', 'Pelaksanaan prosedur dipantau dan penyimpangan diidentifikasi.'],
                ['3', 'Melaporkan hasil pengawasan izin kerja', 'Penutupan izin dilaporkan dan laporan didokumentasikan.'],
            ]],
            ['M.71KKK01.005.1', 'Melakukan Pengukuran Potensi Bahaya di Tempat Kerja', [
                ['1', 'Mempersiapkan pengukuran faktor bahaya di tempat kerja', 'Faktor bahaya dikelompokkan, formulir dan sarana pengukuran disiapkan.'],
                ['2', 'Melaksanakan pengukuran faktor bahaya di tempat kerja', 'Metode dan sampling ditentukan, APD dan alat ukur digunakan sesuai prosedur, serta hasil dibandingkan dengan standar.'],
                ['3', 'Melaporkan hasil pengukuran faktor bahaya', 'Laporan disusun, disampaikan, dan didokumentasikan sesuai prosedur.'],
            ]],
            ['M.71KKK01.007.1', 'Mengelola Tindakan Tanggap Darurat', [
                ['1', 'Merencanakan pelaksanaan tanggap darurat', 'Program, petugas, peralatan, perlengkapan, sistem dan sarana komunikasi diperiksa.'],
                ['2', 'Melaksanakan tanggap darurat', 'Program dan prosedur diterapkan; tim, peralatan, dan komunikasi berfungsi sesuai kondisi darurat.'],
                ['3', 'Mengevaluasi pelaksanaan tanggap darurat', 'Pelaksanaan dievaluasi dan hasilnya disusun, dilaporkan, serta didokumentasikan.'],
            ]],
            ['M.71KKK01.008.1', 'Mengelola Alat Pelindung Diri di Tempat Kerja', [
                ['1', 'Mempersiapkan APD yang diperlukan', 'Jenis, spesifikasi, jumlah, ketersediaan, dan prosedur pengelolaan APD disiapkan.'],
                ['2', 'Memeriksa kondisi APD', 'Kelayakan fisik dan fungsi diperiksa; APD tidak layak tidak digunakan, diganti, dan dimusnahkan.'],
                ['3', 'Melaporkan hasil pengelolaan APD', 'Laporan disusun, disampaikan, dan didokumentasikan.'],
            ]],
            ['M.71KKK01.010.1', 'Mengelola Sistem Dokumentasi K3', [
                ['1', 'Mempersiapkan sistem dokumentasi K3', 'Sumber, pihak terkait, dan media dokumentasi diidentifikasi.'],
                ['2', 'Melakukan pemenuhan sistem dokumentasi K3', 'Prosedur dibuat, media diklasifikasikan, dan dokumen didistribusikan.'],
                ['3', 'Mengevaluasi sistem dokumentasi K3', 'Ketersediaan, aksesibilitas, dan pemutakhiran dokumen ditinjau.'],
                ['4', 'Melaporkan hasil evaluasi sistem dokumentasi K3', 'Laporan disusun, disampaikan, dan didokumentasikan.'],
            ]],
            ['M.71KKK01.013.1', 'Melakukan Investigasi Kecelakaan Kerja', [
                ['1', 'Mempersiapkan kegiatan investigasi kecelakaan kerja', 'Keparahan, keseringan, lokasi, sarana, personel, dan dokumen investigasi diidentifikasi atau disiapkan.'],
                ['2', 'Melaksanakan investigasi kecelakaan kerja', 'Lokasi diamankan, kondisi didokumentasikan, personel terkait ditentukan, penyebab dicari dengan 5W1H, dan rekomendasi dibuat.'],
                ['3', 'Melaporkan hasil investigasi kecelakaan kerja', 'Laporan disusun sesuai peraturan, disampaikan, dan didokumentasikan.'],
            ]],
        ];

        $units = $this->competencyUnits();
        $sections = [];
        foreach ($units as $unit) {
            $unitCode = $unit['code'];
            $title = $unit['title'];
            $questions = [];
            foreach ($unit['elements'] as $element) {
                $elementCode = $element['code'];
                $base = str_replace('.', '_', $unitCode).'_E'.$elementCode;
                $kuk = collect($element['criteria'])->map(fn ($text, $code) => $code.' '.$text)->implode('<br>');
                $scope = ['unit_code' => $unitCode, 'element_code' => $elementCode];
                $questions[] = $this->q($base.'_MANDIRI', 'self_assessment', '<strong>Elemen '.$elementCode.':</strong> '.$element['name'].'<br><small>Kriteria Unjuk Kerja:<br>'.$kuk.'</small>', true, $this->options(['K' => 'K - Saya mampu', 'BK' => 'BK - Belum mampu']), null, $scope);
                $questions[] = $this->q($base.'_BUKTI', 'long_text', 'Tuliskan bukti relevan untuk elemen '.$elementCode.'.', false, null, 'Cantumkan nama bukti, pengalaman, atau lokasi berkas pendukung.', $scope);
                $questions[] = $this->q($base.'_FILE', 'file_upload', 'Unggah bukti pendukung elemen '.$elementCode.' (jika ada).', false, null, null, $scope);
            }
            $sections[] = ['title' => $unitCode.' - '.$title, 'description' => 'Pilih K atau BK dan cantumkan bukti yang relevan.', 'questions' => $questions];
        }
        $sections[] = $this->section('Rekomendasi Asesmen Mandiri', [
            $this->q('APL02_REKOMENDASI', 'radio', 'Rekomendasi untuk asesi: asesmen dapat atau tidak dapat dilanjutkan.', true, $this->options(['dapat' => 'Asesmen dapat dilanjutkan', 'tidak_dapat' => 'Asesmen tidak dapat dilanjutkan'])),
            $this->signature('APL02_TTD_ASESI', 'Tanda tangan asesi.', 'asesi'),
            $this->signature('APL02_TTD_ASESOR', 'Tanda tangan asesor/reviewer.', 'asesor'),
        ]);

        return $this->form('FR.APL.02', 'Asesmen Mandiri', 'pra_asesmen', 'bersama', 'asesor', $sections,
            'Baca setiap elemen, pilih K jika yakin mampu atau BK jika belum mampu, kemudian tuliskan bukti relevan.');
    }

    private function planningForms(): array
    {
        return [
            $this->form('FR.MAPA.01', 'Merencanakan Aktivitas dan Proses Asesmen', 'pra_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Pendekatan Asesmen', [
                    $this->q('MAPA01_POTENSI_ASESI', 'radio', 'Potensi/karakteristik asesi.', true, $this->options(['pelatihan_tertulusur' => 'Hasil pelatihan/pendidikan dengan kurikulum dan fasilitas praktik tertelusur standar kompetensi', 'pelatihan_belum' => 'Hasil pelatihan/pendidikan dengan kurikulum belum berbasis kompetensi', 'pekerja_tertulusur' => 'Pekerja berpengalaman dari tempat kerja tertelusur standar kompetensi', 'pekerja_belum' => 'Pekerja berpengalaman dari tempat kerja belum berbasis kompetensi', 'mandiri' => 'Pelatihan/belajar mandiri atau otodidak'])),
                    $this->q('MAPA01_TUJUAN', 'checkbox', 'Tujuan asesmen.', true, $this->options(['sertifikasi' => 'Sertifikasi', 'pkt' => 'Pengakuan Kompetensi Terkini', 'rpl' => 'Rekognisi Pembelajaran Lampau', 'lainnya' => 'Lainnya'])),
                    $this->q('MAPA01_LINGKUNGAN', 'radio', 'Lingkungan asesmen.', true, $this->options(['nyata' => 'Tempat kerja nyata', 'simulasi' => 'Tempat kerja simulasi'])),
                    $this->q('MAPA01_PELUANG_BUKTI', 'radio', 'Peluang mengumpulkan bukti dalam sejumlah situasi.', true, $this->options(['tersedia' => 'Tersedia', 'terbatas' => 'Terbatas'])),
                    $this->q('MAPA01_HUBUNGAN', 'checkbox', 'Hubungan standar kompetensi.', true, $this->options(['bukti' => 'Bukti pendukung asesmen', 'aktivitas' => 'Aktivitas kerja asesi', 'pembelajaran' => 'Kegiatan pembelajaran'])),
                    $this->q('MAPA01_PELAKSANA', 'checkbox', 'Pihak yang melakukan asesmen.', true, $this->options(['lsp' => 'Lembaga Sertifikasi', 'pelatihan' => 'Organisasi Pelatihan', 'perusahaan' => 'Asesor Perusahaan'])),
                    $this->q('MAPA01_KONFIRMASI', 'checkbox', 'Orang yang relevan untuk konfirmasi.', true, $this->options(['manajer_lsp' => 'Manajer sertifikasi LSP', 'lead' => 'Master/Lead Asesor', 'manajer_pelatihan' => 'Manajer pelatihan', 'supervisor' => 'Manajer/Supervisor tempat kerja'])),
                    $this->q('MAPA01_ACUAN', 'checkbox', 'Standar industri atau tempat kerja.', true, $this->options(['skkni' => 'SKKNI No. 307 Tahun 2017 dan SKKNI No. 38 Tahun 2019', 'kurikulum' => 'Kriteria asesmen kurikulum pelatihan', 'kinerja' => 'Spesifikasi kinerja perusahaan/industri', 'produk' => 'Spesifikasi produk', 'pedoman' => 'SOP K3 dan SOP K3 Laboratorium UNISA Yogyakarta'])),
                ]),
                $this->section('Rencana Asesmen', [
                    $this->q('MAPA01_KELOMPOK_1', 'long_text', 'Kelompok pekerjaan 1 - bukti, jenis bukti, metode dan perangkat untuk unit M.71KKK00.001.1 dan M.71KKK00.002.1.', true),
                    $this->q('MAPA01_KELOMPOK_2', 'long_text', 'Kelompok pekerjaan 2 - bukti, jenis bukti, metode dan perangkat untuk enam unit pelaksanaan K3.', true),
                    $this->q('MAPA01_KELOMPOK_3', 'long_text', 'Kelompok pekerjaan 3 - bukti, jenis bukti, metode dan perangkat untuk unit M.71KKK01.013.1.', true),
                    $this->q('MAPA01_METODE', 'checkbox', 'Metode asesmen yang dipilih.', true, $this->options(['observasi' => 'Observasi langsung/CL', 'terstruktur' => 'Kegiatan terstruktur/DIT', 'tanya_jawab' => 'Tanya jawab/DPL atau DPT', 'portofolio' => 'Verifikasi portofolio/CVP', 'produk' => 'Reviu produk/CRP', 'pihak_ketiga' => 'Verifikasi pihak ketiga/VPK'])),
                ]),
                $this->section('Modifikasi dan Kontekstualisasi', [
                    $this->q('MAPA01_KARAKTERISTIK', 'radio', 'Apakah terdapat karakteristik khusus kandidat?', true, $this->yesNo()),
                    $this->q('MAPA01_KARAKTERISTIK_DETAIL', 'long_text', 'Jika ada, tuliskan karakteristik khusus kandidat.'),
                    $this->q('MAPA01_KEBUTUHAN_TEMPAT', 'radio', 'Apakah ada kebutuhan kontekstualisasi terkait tempat kerja?', true, $this->yesNo()),
                    $this->q('MAPA01_KEBUTUHAN_TEMPAT_DETAIL', 'long_text', 'Jika ada, tuliskan kebutuhan kontekstualisasi.'),
                    $this->q('MAPA01_SARAN_PAKET', 'radio', 'Apakah ada saran dari paket/pengembang pelatihan?', true, $this->yesNo()),
                    $this->q('MAPA01_SARAN_DETAIL', 'long_text', 'Jika ada, tuliskan saran.'),
                    $this->q('MAPA01_PENYESUAIAN_PERANGKAT', 'radio', 'Apakah ada penyesuaian perangkat asesmen?', true, $this->yesNo()),
                    $this->q('MAPA01_PENYESUAIAN_DETAIL', 'long_text', 'Jika ada, tuliskan penyesuaian perangkat.'),
                    $this->q('MAPA01_INTEGRASI', 'radio', 'Apakah ada peluang asesmen terintegrasi?', true, $this->yesNo()),
                    $this->q('MAPA01_INTEGRASI_DETAIL', 'long_text', 'Jika ada, tuliskan peluang dan perubahan yang diperlukan.'),
                    $this->signature('MAPA01_TTD', 'Tanda tangan konfirmasi orang yang relevan.', 'asesor'),
                ]),
            ]),
            $this->form('FR.MAPA.02', 'Peta Instrumen Asesmen Hasil Pendekatan dan Perencanaan Asesmen', 'pra_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Pemetaan Instrumen', [
                    $this->q('MAPA02_KELOMPOK_1', 'checkbox', 'Instrumen kelompok pekerjaan merencanakan penerapan prinsip K3.', true, $this->instrumentOptions()),
                    $this->q('MAPA02_KELOMPOK_1_POTENSI', 'radio', 'Kategori potensi asesi kelompok 1.', true, $this->potentialOptions()),
                    $this->q('MAPA02_KELOMPOK_2', 'checkbox', 'Instrumen kelompok pekerjaan melaksanakan penerapan prinsip K3.', true, $this->instrumentOptions()),
                    $this->q('MAPA02_KELOMPOK_2_POTENSI', 'radio', 'Kategori potensi asesi kelompok 2.', true, $this->potentialOptions()),
                    $this->q('MAPA02_KELOMPOK_3', 'checkbox', 'Instrumen kelompok pekerjaan mengevaluasi penerapan prinsip K3.', true, $this->instrumentOptions()),
                    $this->q('MAPA02_KELOMPOK_3_POTENSI', 'radio', 'Kategori potensi asesi kelompok 3.', true, $this->potentialOptions()),
                    $this->signature('MAPA02_TTD_PENYUSUN', 'Tanda tangan penyusun.', 'asesor'),
                ]),
            ]),
        ];
    }

    private function agreementForms(): array
    {
        return [
            $this->form('FR.AK.01', 'Persetujuan Asesmen dan Kerahasiaan', 'pra_asesmen', 'bersama', 'asesor', [
                $this->section('Persetujuan', [
                    $this->q('AK01_BUKTI', 'checkbox', 'Bukti yang akan dikumpulkan.', true, $this->options(['portofolio' => 'Hasil verifikasi portofolio', 'produk' => 'Hasil reviu produk', 'observasi' => 'Hasil observasi langsung', 'terstruktur' => 'Hasil kegiatan terstruktur', 'tertulis' => 'Daftar pertanyaan tertulis', 'lisan' => 'Daftar pertanyaan lisan', 'wawancara' => 'Daftar pertanyaan wawancara', 'lainnya' => 'Lainnya'])),
                    $this->q('AK01_HARI_TANGGAL', 'date', 'Hari/tanggal pelaksanaan asesmen.', true),
                    $this->q('AK01_WAKTU', 'short_text', 'Waktu pelaksanaan asesmen.', true),
                    $this->q('AK01_TUK', 'radio', 'Tempat Uji Kompetensi.', true, $this->options(['sewaktu' => 'Sewaktu', 'tempat_kerja' => 'Tempat Kerja', 'mandiri' => 'Mandiri'])),
                    $this->q('AK01_RENCANA', 'radio', 'Saya menyetujui rencana asesmen yang telah dijelaskan.', true, $this->yesNo()),
                    $this->q('AK01_HAK', 'radio', 'Saya memahami hak, prosedur asesmen, dan proses banding.', true, $this->yesNo()),
                    $this->q('AK01_KERAHASIAAN', 'radio', 'Saya menyetujui ketentuan kerahasiaan dan penggunaan bukti asesmen.', true, $this->yesNo()),
                    $this->q('AK01_CATATAN', 'long_text', 'Catatan persetujuan atau kebutuhan khusus.'),
                    $this->signature('AK01_TTD_ASESI', 'Tanda tangan asesi.', 'asesi'),
                    $this->signature('AK01_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
                ]),
            ]),
            $this->form('FR.AK.04', 'Banding Asesmen', 'pasca_asesmen', 'asesi', 'admin', [
                $this->section('Pengajuan Banding', [
                    $this->q('AK04_DIJELASKAN', 'radio', 'Apakah proses banding telah dijelaskan kepada Anda?', true, $this->yesNo()),
                    $this->q('AK04_DISKUSI', 'radio', 'Apakah Anda telah mendiskusikan banding dengan asesor?', true, $this->yesNo()),
                    $this->q('AK04_ORANG_LAIN', 'radio', 'Apakah Anda mau melibatkan orang lain untuk membantu dalam proses banding?', true, $this->yesNo()),
                    $this->q('AK04_SKEMA', 'short_text', 'Skema sertifikasi yang keputusan asesmennya diajukan banding.', true),
                    $this->q('AK04_NOMOR_SKEMA', 'short_text', 'Nomor skema sertifikasi.', true),
                    $this->q('AK04_KEPUTUSAN', 'long_text', 'Keputusan asesmen yang diajukan banding.', true),
                    $this->q('AK04_ALASAN', 'long_text', 'Alasan banding dan bagian proses yang tidak disetujui.', true),
                    $this->q('AK04_BUKTI', 'file_upload', 'Unggah bukti pendukung banding.'),
                    $this->signature('AK04_TTD', 'Tanda tangan asesi dan tanggal.', 'asesi'),
                ]),
            ]),
            $this->form('FR.AK.07', 'Ceklis Penyesuaian yang Wajar dan Beralasan', 'pra_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Bagian A - Penyesuaian Karakteristik Asesi', [
                    $this->adjustment('AK07_A1', 'Keterbatasan terhadap persyaratan bahasa, literasi, dan numerasi.', ['Dukungan pembaca/penerjemah/pelayan/penulis', 'Asesmen verbal dengan gambar/diagram/visual', 'Menggunakan hasil produksi', 'Ceklis observasi/demonstrasi', 'Daftar instruksi terstruktur']),
                    $this->adjustment('AK07_A2', 'Penyediaan dukungan pembaca, penerjemah, pelayan, atau penulis.', ['Pertanyaan lisan dengan gambar/diagram/visual', 'Pertanyaan wawancara dengan gambar/diagram/visual']),
                    $this->adjustment('AK07_A3', 'Penggunaan teknologi adaptif atau peralatan khusus.', ['Ceklis observasi/demonstrasi', 'Pertanyaan lisan', 'Pertanyaan tertulis', 'Pertanyaan wawancara', 'Daftar instruksi terstruktur', 'Ceklis verifikasi portofolio', 'Dukungan operator komputer']),
                    $this->adjustment('AK07_A4', 'Pelaksanaan asesmen secara fleksibel karena keletihan atau keperluan pengobatan.', ['Juru tulis', 'Kamerawan/perekam video atau audio', 'Waktu lebih panjang', 'Tugas dengan waktu lebih pendek', 'Instruksi spesifik bertingkat']),
                    $this->adjustment('AK07_A5', 'Penyediaan peralatan asesmen berupa braille atau audio/video.', ['Pertanyaan lisan', 'Pertanyaan wawancara']),
                    $this->adjustment('AK07_A6', 'Penyesuaian tempat fisik atau lingkungan asesmen.', ['Pertanyaan lisan', 'Pertanyaan tertulis', 'Pertanyaan wawancara', 'Ceklis verifikasi portofolio', 'Ceklis reviu produk', 'Daftar instruksi terstruktur']),
                    $this->adjustment('AK07_A7', 'Pertimbangan umur, usia lanjut, atau gender asesi.', ['Studi kasus/daftar instruksi terstruktur', 'Huruf instrumen berukuran normal', 'Asesor dengan jenis kelamin sama', 'Instrumen yang sama tanpa pembeda gender']),
                    $this->adjustment('AK07_A8', 'Pertimbangan budaya, tradisi, atau agama.', ['Studi kasus/daftar instruksi terstruktur', 'Asesor tanpa pertimbangan budaya/tradisi/agama', 'Instrumen yang sama tanpa pembeda budaya/tradisi/agama']),
                ]),
                $this->section('Bagian B - Penyesuaian Rencana Asesmen', [
                    $this->q('AK07_B1', 'radio', 'Apakah rencana asesmen tervalidasi dibuat menggunakan acuan pembanding minimal standar kompetensi kerja?', true, $this->yesNo()),
                    $this->q('AK07_B1_KEPUTUSAN', 'long_text', 'Tuliskan standar industri, SOP, dan regulasi teknik.'),
                    $this->q('AK07_B2', 'radio', 'Apakah rencana asesmen tervalidasi sesuai dengan potensi asesi yang akan diujikan?', true, $this->yesNo()),
                    $this->q('AK07_B2_KEPUTUSAN', 'long_text', 'Tuliskan metode dan instrumen asesmen.'),
                    $this->q('AK07_B3', 'radio', 'Apakah rencana asesmen tervalidasi sesuai dengan konteks asesi berdasarkan APL.01 dan APL.02 tervalidasi?', true, $this->yesNo()),
                    $this->q('AK07_B3_KEPUTUSAN', 'long_text', 'Tuliskan metode dan instrumen asesmen.'),
                ]),
                $this->section('Hasil Penyesuaian', [
                    $this->q('AK07_HASIL_A_ACUAN', 'long_text', 'A. Acuan pembanding asesmen.'),
                    $this->q('AK07_HASIL_A_METODE', 'long_text', 'A. Metode asesmen.'),
                    $this->q('AK07_HASIL_A_INSTRUMEN', 'long_text', 'A. Instrumen asesmen.'),
                    $this->q('AK07_HASIL_B_ACUAN', 'long_text', 'B. Acuan pembanding asesmen.'),
                    $this->q('AK07_HASIL_B_METODE', 'long_text', 'B. Metode asesmen.'),
                    $this->q('AK07_HASIL_B_INSTRUMEN', 'long_text', 'B. Instrumen asesmen.'),
                    $this->signature('AK07_TTD_ASESOR', 'Persetujuan dan tanda tangan asesor.', 'asesor'),
                ]),
            ]),
        ];
    }

    private function assessmentInstruments(): array
    {
        $group1 = ['M.71KKK00.001.1', 'M.71KKK00.002.1'];
        $group2 = ['M.71KKK01.003.1', 'M.71KKK01.004.1', 'M.71KKK01.005.1', 'M.71KKK01.007.1', 'M.71KKK01.008.1', 'M.71KKK01.010.1'];
        $group3 = ['M.71KKK01.013.1'];
        $allUnits = array_merge($group1, $group2, $group3);
        $unitOptions = $this->options([
            'M.71KKK00.001.1' => 'Peraturan dan standar K3', 'M.71KKK00.002.1' => 'Survei potensi bahaya',
            'M.71KKK01.003.1' => 'Komunikasi K3', 'M.71KKK01.004.1' => 'Izin kerja',
            'M.71KKK01.005.1' => 'Pengukuran faktor bahaya', 'M.71KKK01.007.1' => 'Tanggap darurat',
            'M.71KKK01.008.1' => 'APD', 'M.71KKK01.010.1' => 'Dokumentasi K3',
            'M.71KKK01.013.1' => 'Investigasi kecelakaan kerja',
        ]);

        return [
            $this->form('FR.IA.01', 'Ceklis Observasi Aktivitas di Tempat Kerja atau Tempat Kerja Simulasi', 'asesmen', 'asesor', 'lead_asesor', $this->ia01Sections()),
            $this->form('FR.IA.02', 'Tugas Praktik Demonstrasi', 'asesmen', 'asesor', 'lead_asesor', [
                $this->section('Skenario 1 - Merencanakan Penerapan Prinsip K3', [
                    $this->q('IA02_S1_SITUASI', 'information', 'Situasi: penanganan tumpahan Bahan Berbahaya dan Beracun (B3) di Laboratorium Karya Sehat dilakukan tanpa identifikasi B3 dan tanpa spill kit yang tepat.'),
                    $this->q('IA02_S1_TUGAS_1', 'practice_task', 'Mengidentifikasi potensi bahaya B3 dan membuat laporan Hazard Identification berdasarkan peraturan dan standar pengujian K3.', true, null, null, ['unit_codes' => $group1]),
                    $this->q('IA02_S1_TUGAS_2', 'practice_task', 'Mengidentifikasi APD yang sesuai untuk penanganan tumpahan B3.', true, null, null, ['unit_codes' => $group1]),
                    $this->q('IA02_S1_HASIL', 'assessor_observation', 'Asesi mampu mengidentifikasi potensi bahaya B3 dan memilih APD yang sesuai dalam waktu 20 menit.', true, $this->achieved(), null, ['unit_codes' => $group1]),
                    $this->q('IA02_S1_BUKTI', 'file_upload', 'Unggah formulir Hazard Identification/hasil tugas skenario 1.'),
                ]),
                $this->section('Skenario 2 - Melaksanakan Penerapan Prinsip K3', [
                    $this->q('IA02_S2_SITUASI', 'information', 'Situasi: terjadi tumpahan sampel pada tangan petugas saat pemeriksaan HBsAg karena APD tidak lengkap di Laboratorium RS UNISA.'),
                    $this->q('IA02_S2_TUGAS_1', 'practice_task', 'Membuat poster A4 tentang penggunaan APD dan risiko jika tidak menggunakan APD lengkap dengan bahasa formal.', true, null, null, ['unit_codes' => $group2]),
                    $this->q('IA02_S2_TUGAS_2', 'practice_task', 'Memberi nama file: NAMA MAHASISWA_NIM_PRODI_POSTER PENGGUNAAN APD DAN RISIKONYA.', true, null, null, ['unit_codes' => $group2]),
                    $this->q('IA02_S2_TUGAS_3', 'practice_task', 'Mengirimkan hasil poster ke tautan yang ditentukan dalam waktu 30 menit.', true, null, null, ['unit_codes' => $group2]),
                    $this->q('IA02_S2_HASIL', 'assessor_observation', 'Poster mencakup komunikasi penggunaan APD dan risiko tidak menggunakan APD lengkap.', true, $this->achieved(), null, ['unit_codes' => $group2]),
                    $this->q('IA02_S2_BUKTI', 'file_upload', 'Unggah poster hasil tugas skenario 2.', true),
                ]),
                $this->section('Skenario 3 - Mengevaluasi Penerapan Prinsip K3', [
                    $this->q('IA02_S3_SITUASI', 'information', 'Situasi: reagen mengandung antigen sifilis tumpah saat pemeriksaan pasien di Laboratorium Imunologi RS UNISA Yogyakarta.'),
                    $this->q('IA02_S3_TUGAS_1', 'practice_task', 'Memilih dan mengisi formulir investigasi kecelakaan yang sesuai.', true, null, null, ['unit_codes' => $group3]),
                    $this->q('IA02_S3_TUGAS_2', 'practice_task', 'Menyusun laporan kejadian kecelakaan serta melengkapi tanda tangan pelapor dan saksi dalam waktu 10 menit.', true, null, null, ['unit_codes' => $group3]),
                    $this->q('IA02_S3_HASIL', 'assessor_observation', 'Asesi dapat menyusun laporan kejadian kecelakaan dengan benar.', true, $this->achieved(), null, ['unit_codes' => $group3]),
                    $this->q('IA02_S3_BUKTI', 'file_upload', 'Unggah laporan investigasi kecelakaan.'),
                    $this->signature('IA02_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
                ]),
            ]),
            $this->form('FR.IA.03', 'Pertanyaan untuk Mendukung Observasi', 'asesmen', 'asesor', 'lead_asesor', [
                $this->section('Pertanyaan Pendukung', [
                    $this->oral('IA03_1', 'Bagaimana cara menentukan metode komunikasi K3 pada rekan kerja yang kurang disiplin menggunakan APD saat mengerjakan pemeriksaan kesehatan?', 'Menentukan metode komunikasi, mencari penyebab ketidakdisiplinan, edukasi risiko dan APD, memberi contoh, serta menindaklanjuti kepatuhan.', ['unit_codes' => $allUnits]),
                    $this->oral('IA03_2', 'Bagaimana cara melakukan identifikasi limbah B3 di laboratorium?', 'Kenali sumber dan karakteristik bahaya, pisahkan sesuai kategori, beri label, simpan dan kelola sesuai SOP/peraturan.', ['unit_codes' => $allUnits]),
                    $this->oral('IA03_3', 'Bagaimana tindakan ketika ditugasi melakukan pemeriksaan di luar laboratorium dengan APD yang berbeda?', 'Pelajari risiko dan SOP lokasi, periksa kelayakan APD, gunakan dengan benar, dan minta arahan bila belum memahami.', ['unit_codes' => $allUnits]),
                    $this->oral('IA03_4', 'Bagaimana langkah mengelola penyimpanan dokumentasi di laboratorium?', 'Kelompokkan, beri label, simpan aman secara fisik/digital, lakukan backup, jaga kerahasiaan, dan arsipkan sesuai SOP.', ['unit_codes' => $allUnits]),
                    $this->q('IA03_UMPAN_BALIK', 'long_text', 'Umpan balik untuk asesi.'),
                    $this->signature('IA03_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
                ]),
            ]),
            $this->form('FR.IA.07', 'Daftar Pertanyaan Lisan', 'asesmen', 'asesor', 'lead_asesor', [
                $this->section('Pertanyaan Lisan', [
                    $this->oral('IA07_1', 'Sebutkan tiga alat pelindung diri K3 yang sering digunakan di laboratorium kimia klinik.', 'Masker, sarung tangan/handscoon, jas laboratorium, atau sepatu keselamatan.', ['unit_codes' => $allUnits]),
                    $this->oral('IA07_2', 'Sebutkan langkah-langkah melakukan identifikasi bahaya di laboratorium.', 'Inspeksi langsung, diskusi dengan laboran, periksa catatan kecelakaan/PAK, dan gunakan checklist.', ['unit_codes' => $allUnits]),
                    $this->oral('IA07_3', 'Bagaimana cara mencegah cedera akibat peralatan tajam atau pecah di laboratorium?', 'Gunakan dan simpan alat dengan hati-hati, pakai APD/penjepit, periksa alat rutin, dan jangan gunakan kaca retak.', ['unit_codes' => $allUnits]),
                    $this->q('IA07_HASIL', 'assessor_observation', 'Aspek pengetahuan seluruh unit kompetensi yang diujikan.', true, $this->achieved()),
                    $this->q('IA07_BELUM', 'long_text', 'Tuliskan unit kompetensi, elemen, dan KUK yang belum tercapai.'),
                    $this->q('IA07_UMPAN_BALIK', 'long_text', 'Umpan balik untuk asesi.'),
                    $this->signature('IA07_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
                ]),
            ]),
        ];
    }

    private function postAssessmentForms(): array
    {
        $feedback = [
            'Mendapat penjelasan memadai mengenai proses asesmen/uji kompetensi.',
            'Diberi kesempatan mempelajari standar kompetensi dan menilai diri.',
            'Diberi kesempatan menegosiasikan metode, instrumen, sumber, dan jadwal asesmen.',
            'Asesor menggali seluruh bukti pendukung yang relevan.',
            'Diberi kesempatan mendemonstrasikan kompetensi.',
            'Mendapat penjelasan memadai mengenai keputusan asesmen.',
            'Asesor memberi umpan balik dan tindak lanjut.',
            'Asesor dan asesi mempelajari serta menandatangani dokumen asesmen.',
            'Mendapat jaminan kerahasiaan hasil dan penanganan dokumen.',
            'Asesor menggunakan komunikasi efektif selama asesmen.',
        ];

        return [
            $this->form('FR.AK.02', 'Rekaman Asesmen Kompetensi', 'pasca_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Rekaman Bukti dan Keputusan', array_merge($this->ak02UnitEvidenceQuestions(), [
                    $this->q('AK02_REKOMENDASI', 'radio', 'Rekomendasi hasil asesmen.', true, $this->options(['K' => 'Kompeten', 'BK' => 'Belum Kompeten'])),
                    $this->q('AK02_TINDAK_LANJUT', 'long_text', 'Tindak lanjut atau asesmen tambahan yang dibutuhkan.'),
                    $this->q('AK02_OBSERVASI', 'long_text', 'Komentar/observasi asesor.'),
                    $this->signature('AK02_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
                ])),
            ]),
            $this->form('FR.AK.03', 'Umpan Balik dan Catatan Asesmen', 'pasca_asesmen', 'asesi', 'asesor', [
                $this->section('Umpan Balik Asesi', array_merge(
                    array_map(fn ($label, $index) => $this->q('AK03_'.($index + 1), 'radio', $label, true, $this->yesNo()), $feedback, array_keys($feedback)),
                    [$this->q('AK03_CATATAN', 'long_text', 'Catatan/komentar lainnya.'), $this->signature('AK03_TTD', 'Tanda tangan asesi.', 'asesi')]
                )),
            ]),
            $this->form('FR.AK.05', 'Laporan Asesmen', 'pasca_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Laporan', [
                    $this->q('AK05_REKOMENDASI', 'radio', 'Rekomendasi asesi.', true, $this->options(['K' => 'Kompeten', 'BK' => 'Belum Kompeten'])),
                    $this->q('AK05_KETERANGAN', 'long_text', 'Kode dan judul unit yang dinyatakan belum kompeten.'),
                    $this->q('AK05_ASPEK', 'long_text', 'Aspek negatif dan positif dalam asesmen.'),
                    $this->q('AK05_PENOLAKAN', 'long_text', 'Pencatatan penolakan hasil asesmen.'),
                    $this->q('AK05_SARAN', 'long_text', 'Saran perbaikan untuk asesor/personel terkait.'),
                    $this->signature('AK05_TTD', 'Tanda tangan asesor.', 'asesor'),
                ]),
            ]),
            $this->form('FR.AK.06', 'Meninjau Proses Asesmen', 'pasca_asesmen', 'asesor', 'lead_asesor', [
                $this->section('Prinsip Asesmen', [
                    $this->q('AK06_RENCANA', 'checkbox', 'Rencana asesmen.', true, $this->principleOptions()),
                    $this->q('AK06_PERSIAPAN', 'checkbox', 'Persiapan asesmen.', true, $this->principleOptions()),
                    $this->q('AK06_IMPLEMENTASI', 'checkbox', 'Implementasi asesmen.', true, $this->principleOptions()),
                    $this->q('AK06_KEPUTUSAN', 'checkbox', 'Keputusan asesmen.', true, $this->principleOptions()),
                    $this->q('AK06_UMPAN_BALIK', 'checkbox', 'Umpan balik asesmen.', true, $this->principleOptions()),
                    $this->q('AK06_REKOMENDASI_PRINSIP', 'long_text', 'Rekomendasi peningkatan berdasarkan prinsip asesmen.'),
                ]),
                $this->section('Dimensi Kompetensi', [
                    $this->q('AK06_DIMENSI', 'checkbox', 'Dimensi kompetensi yang dipenuhi secara konsisten.', true, $this->options(['TS' => 'Task Skills', 'TMS' => 'Task Management Skills', 'CMS' => 'Contingency Management Skills', 'JRES' => 'Job Role/Environment Skills', 'TRS' => 'Transfer Skills'])),
                    $this->q('AK06_REKOMENDASI_DIMENSI', 'long_text', 'Rekomendasi peningkatan berdasarkan dimensi kompetensi.'),
                    $this->signature('AK06_TTD', 'Nama, tanggal, dan tanda tangan lead asesor/asesor.', 'asesor'),
                ]),
            ]),
        ];
    }

    private function ia01Sections(): array
    {
        $sections = [];
        foreach ($this->competencyUnits() as $unit) {
            $questions = [];
            foreach ($unit['elements'] as $element) {
                foreach ($element['criteria'] as $kukCode => $kuk) {
                    $questions[] = $this->q(
                        'IA01_'.str_replace('.', '_', $unit['code']).'_KUK_'.str_replace('.', '_', $kukCode),
                        'assessor_observation',
                        '<strong>Elemen '.$element['code'].': '.$element['name'].'</strong><br>'.$kukCode.' '.$kuk,
                        true,
                        $this->options(['ya' => 'Ya', 'tidak' => 'Tidak', 'lanjut' => 'Penilaian lanjut']),
                        'Standar industri/tempat kerja: SOP K3 UNISA Yogyakarta dan SOP K3 Laboratorium UNISA Yogyakarta.',
                        ['unit_code' => $unit['code'], 'element_code' => $element['code'], 'kuk_code' => $kukCode]
                    );
                }
            }
            $questions[] = $this->q('IA01_'.str_replace('.', '_', $unit['code']).'_CATATAN', 'long_text', 'Catatan penilaian lanjut untuk unit ini.');
            $sections[] = $this->section($unit['code'].' - '.$unit['title'], $questions);
        }
        $sections[] = $this->section('Umpan Balik dan Rekomendasi', [
            $this->q('IA01_UMPAN_BALIK', 'long_text', 'Umpan balik untuk asesi.'),
            $this->q('IA01_REKOMENDASI', 'radio', 'Rekomendasi hasil observasi.', true, $this->options(['K' => 'Kompeten', 'BK' => 'Belum Kompeten'])),
            $this->q('IA01_BELUM_KOMPETEN', 'long_text', 'Jika belum kompeten, tuliskan kelompok pekerjaan, unit, elemen, dan KUK.'),
            $this->signature('IA01_TTD_ASESOR', 'Tanda tangan asesor.', 'asesor'),
        ]);
        return $sections;
    }

    private function competencyUnits(): array
    {
        return [
            $this->unit('M.71KKK00.001.1', 'Menerapkan Peraturan Perundang-undangan dan Standar dalam Pengujian Keselamatan dan Kesehatan Kerja', [
                $this->element('1', 'Mengidentifikasi pengujian K3 sesuai peraturan perundang-undangan dan standar pengujian K3 yang digunakan di tempat kerja', [
                    '1.1' => 'Peraturan perundang-undangan dan standar K3 diidentifikasi sebagai dasar perlindungan tenaga kerja.',
                    '1.2' => 'Peraturan perundang-undangan dan standar pengujian K3 di tempat kerja dikelompokkan berdasarkan bidang pengujian K3.',
                ]),
                $this->element('2', 'Melaksanakan peraturan perundang-undangan dan standar pengujian K3 di tempat kerja', [
                    '2.1' => 'Peraturan perundang-undangan dilaksanakan sesuai dengan bidang pengujian K3.',
                    '2.2' => 'Standar pengujian dilaksanakan sesuai dengan bidang pengujian K3.',
                    '2.3' => 'Peraturan perundang-undangan dan standar pengujian K3 didokumentasikan sesuai dengan bidang pengujian K3.',
                ]),
            ]),
            $this->unit('M.71KKK00.002.1', 'Melakukan Survei Potensi Bahaya K3', [
                $this->element('1', 'Mengidentifikasi potensi bahaya K3 di tempat kerja', [
                    '1.1' => 'Sarana untuk survei potensi bahaya disiapkan sesuai jenis tempat kerja.',
                    '1.2' => 'Potensi bahaya K3 di tempat kerja diidentifikasi sesuai diagram alur proses produksi.',
                    '1.3' => 'Sumber bahaya diidentifikasi sesuai potensi bahaya K3 di tempat kerja.',
                    '1.4' => 'Tenaga kerja yang berisiko diidentifikasi sesuai pajanan bahaya K3 di tempat kerja.',
                    '1.5' => 'Lingkungan tempat kerja yang berisiko diidentifikasi sesuai potensi bahaya K3.',
                    '1.6' => 'Informasi pengendalian bahaya yang sudah dilakukan diidentifikasi sesuai hirarki pengendalian.',
                ]),
                $this->element('2', 'Menetapkan jenis pengujian K3', [
                    '2.1' => 'Sampling pengujian ditentukan sesuai dengan hasil identifikasi potensi bahaya K3 di tempat kerja.',
                    '2.2' => 'Pemetaan potensi bahaya K3 dibuat sesuai dengan penentuan sampling pengujian K3.',
                    '2.3' => 'Tenaga kerja yang terkena dampak potensi bahaya ditentukan sesuai hasil identifikasi potensi bahaya K3 di tempat kerja.',
                    '2.4' => 'Jenis pengujian K3 ditentukan sesuai dengan potensi bahaya K3.',
                ]),
            ]),
            $this->unit('M.71KKK01.003.1', 'Melakukan Komunikasi Keselamatan dan Kesehatan Kerja', [
                $this->element('1', 'Merencanakan proses kegiatan komunikasi K3', [
                    '1.1' => 'Permasalahan K3 yang terjadi di tempat kerja diidentifikasi berdasarkan masukan dari pekerja.',
                    '1.2' => 'Permasalahan K3 yang terjadi di luar perusahaan dipertimbangkan sebagai masukan.',
                    '1.3' => 'Petugas K3 yang menangani komunikasi ditentukan tugas dan tanggung jawabnya.',
                    '1.4' => 'Sumber dan cara akses informasi diidentifikasi sesuai permasalahan K3.',
                ]),
                $this->element('2', 'Melaksanakan proses komunikasi K3', [
                    '2.1' => 'Informasi tentang efektifitas pencegahan bahaya di tempat kerja dikomunikasikan kepada tenaga kerja sebagai masukan internal.',
                    '2.2' => 'Informasi K3 yang membutuhkan kerjasama secara eksternal dikonsultasikan dengan pihak pemangku kepentingan.',
                    '2.3' => 'Informasi dan masukan secara internal dan eksternal dicatat sebagai bahan penanganan masalah K3 di tempat kerja.',
                    '2.4' => 'Informasi dan masukan secara internal dan eksternal tentang penanganan masalah K3 dikonfirmasikan dengan rekan kerja.',
                    '2.5' => 'Bahan komunikasi K3 dibuat sesuai hasil pembicaraan dengan rekan kerja.',
                    '2.6' => 'Metode komunikasi K3 dibuat sesuai dengan kebutuhan.',
                ]),
                $this->element('3', 'Memonitor pelaksanaan tindak lanjut hasil komunikasi K3', [
                    '3.1' => 'Bahan komunikasi K3 didistribusikan ke pihak terkait sesuai pengendalian permasalahan K3 di tempat kerja.',
                    '3.2' => 'Status penyebaran informasi dan penerapannya dipastikan sudah dilakukan oleh pihak terkait.',
                ]),
                $this->element('4', 'Melaporkan kegiatan komunikasi K3', [
                    '4.1' => 'Laporan hasil komunikasi K3 disusun sesuai format yang berlaku.',
                    '4.2' => 'Laporan hasil komunikasi K3 disampaikan ke atasan dan pihak terkait.',
                    '4.3' => 'Laporan hasil komunikasi K3 didokumentasikan sesuai prosedur.',
                ]),
            ]),
            $this->unit('M.71KKK01.004.1', 'Mengawasi Penerapan Izin Kerja', [
                $this->element('1', 'Mempersiapkan izin kerja', ['1.1' => 'Jenis izin kerja diidentifikasi sesuai dengan aktivitas kerja.', '1.2' => 'Prosedur izin kerja diidentifikasi sesuai ketentuan K3.']),
                $this->element('2', 'Mengawasi penerapan izin kerja di tempat kerja', ['2.1' => 'Pelaksanaan prosedur kerja dipantau sesuai izin kerja.', '2.2' => 'Penyimpangan terhadap persyaratan izin kerja diidentifikasi sesuai peraturan yang berlaku.']),
                $this->element('3', 'Melaporkan hasil pengawasan izin kerja', ['3.1' => 'Penutupan izin kerja dilaporkan setelah pekerjaan selesai atau batas waktu yang ditentukan sesuai dengan prosedur.', '3.2' => 'Laporan didokumentasikan sesuai prosedur.']),
            ]),
            $this->unit('M.71KKK01.005.1', 'Melakukan Pengukuran Potensi Bahaya di Tempat Kerja', [
                $this->element('1', 'Mempersiapkan pengukuran faktor bahaya di tempat kerja', ['1.1' => 'Faktor bahaya di tempat kerja dikelompokkan sesuai hasil identifikasi.', '1.2' => 'Formulir disiapkan untuk pengukuran faktor bahaya di tempat kerja.', '1.3' => 'Sarana pengukuran disiapkan untuk mengambil data bahaya di tempat kerja.']),
                $this->element('2', 'Melaksanakan pengukuran faktor bahaya di tempat kerja', ['2.1' => 'Metode pengukuran faktor bahaya di tempat kerja ditentukan sesuai strategi sampling.', '2.2' => 'Alat Pelindung Diri (APD) digunakan sesuai faktor bahaya di lingkungan kerja.', '2.3' => 'Pengukuran faktor bahaya di tempat kerja dilakukan sesuai standar dan pemetaan titik sampling.', '2.4' => 'Alat ukur faktor bahaya K3 digunakan sesuai prosedur.', '2.5' => 'Hasil pengukuran dibandingkan dengan peraturan perundang-undangan atau standar yang berlaku.']),
                $this->element('3', 'Melaporkan hasil pengukuran faktor bahaya di tempat kerja', ['3.1' => 'Laporan hasil pengukuran disusun sesuai format yang berlaku.', '3.2' => 'Laporan disampaikan kepada atasan langsung.', '3.3' => 'Laporan didokumentasikan sesuai prosedur.']),
            ]),
            $this->unit('M.71KKK01.007.1', 'Mengelola Tindakan Tanggap Darurat', [
                $this->element('1', 'Merencanakan pelaksanaan tanggap darurat di tempat kerja', ['1.1' => 'Program, petugas dan peralatan tanggap darurat serta perlengkapannya diperiksa sesuai persyaratan K3.', '1.2' => 'Sistem dan sarana komunikasi untuk tanggap darurat dipastikan masih berfungsi dengan baik.']),
                $this->element('2', 'Melaksanakan tanggap darurat di tempat kerja', ['2.1' => 'Program dan prosedur tanggap darurat diterapkan sesuai kondisi darurat yang terjadi.', '2.2' => 'Tim tanggap darurat dipastikan menjalankan peran dan tugasnya.', '2.3' => 'Peralatan tanggap darurat serta perlengkapannya digunakan sesuai kondisi darurat yang terjadi.', '2.4' => 'Sistem dan sarana komunikasi tanggap darurat digunakan sesuai kondisi darurat yang terjadi.']),
                $this->element('3', 'Mengevaluasi hasil pelaksanaan tanggap darurat di tempat kerja', ['3.1' => 'Pelaksanaan tanggap darurat dievaluasi sesuai dengan prosedur yang berlaku.', '3.2' => 'Laporan hasil evaluasi disusun sesuai dengan format yang berlaku.', '3.3' => 'Hasil evaluasi dilaporkan kepada pihak terkait di tempat kerja.', '3.4' => 'Laporan hasil evaluasi didokumentasikan sesuai dengan prosedur.']),
            ]),
            $this->unit('M.71KKK01.008.1', 'Mengelola Alat Pelindung Diri di Tempat Kerja', [
                $this->element('1', 'Mempersiapkan APD yang diperlukan di tempat kerja', ['1.1' => 'Jenis dan spesifikasi APD ditentukan sesuai faktor bahaya di tempat kerja.', '1.2' => 'Jumlah dan ketersediaan APD diidentifikasi sesuai kebutuhan di tempat kerja.', '1.3' => 'Prosedur penyimpanan, penggunaan, pemeriksaan dan pemusnahan dipersiapkan sesuai dengan standar yang berlaku.']),
                $this->element('2', 'Memeriksa kondisi APD di tempat kerja', ['2.1' => 'Kelayakan fisik APD diperiksa sesuai dengan prosedur.', '2.2' => 'Kelayakan fungsi APD diperiksa sesuai dengan prosedur.', '2.3' => 'Kondisi APD yang tidak layak dipastikan tidak digunakan, diganti dan dimusnahkan sesuai peraturan perundang-undangan atau standar yang berlaku.']),
                $this->element('3', 'Melaporkan hasil pengelolaan APD', ['3.1' => 'Laporan hasil pengelolaan APD disusun sesuai format yang berlaku.', '3.2' => 'Laporan hasil pengelolaan APD disampaikan ke pihak terkait.', '3.3' => 'Laporan hasil pengelolaan APD didokumentasikan sesuai prosedur yang berlaku.']),
            ]),
            $this->unit('M.71KKK01.010.1', 'Mengelola Sistem Dokumentasi K3', [
                $this->element('1', 'Mempersiapkan sistem dokumentasi K3 yang dibutuhkan di tempat kerja', ['1.1' => 'Sumber dokumentasi K3 diidentifikasi berdasarkan kebutuhan aktivitas kerja.', '1.2' => 'Pihak yang terkait diidentifikasi sesuai kebutuhan aktivitas kerja.', '1.3' => 'Jenis media dokumentasi K3 diidentifikasi sesuai kebutuhan aktivitas kerja.']),
                $this->element('2', 'Melakukan pemenuhan sistem dokumentasi K3', ['2.1' => 'Prosedur pengendalian dokumen K3 dibuat berdasarkan kebutuhan aktivitas kerja.', '2.2' => 'Jenis dan media penyebaran dokumen K3 diklasifikasikan berdasarkan kebutuhan aktivitas kerja.', '2.3' => 'Dokumen K3 didistribusikan kepada pihak yang terkait.']),
                $this->element('3', 'Mengevaluasi sistem dokumentasi K3', ['3.1' => 'Ketersediaan dokumen K3 ditinjau berdasarkan potensi bahaya dan tingkat risiko.', '3.2' => 'Dokumen K3 dipastikan mudah diakses.', '3.3' => 'Dokumen K3 dilakukan pemutakhiran sesuai peraturan perundang-undangan K3 dan perkembangan yang berlaku.']),
                $this->element('4', 'Melaporkan hasil evaluasi sistem dokumentasi K3', ['4.1' => 'Laporan hasil evaluasi dokumen K3 disusun sesuai format yang berlaku.', '4.2' => 'Laporan hasil evaluasi disampaikan kepada pihak terkait.', '4.3' => 'Laporan didokumentasikan sesuai prosedur.']),
            ]),
            $this->unit('M.71KKK01.013.1', 'Melakukan Investigasi Kecelakaan Kerja', [
                $this->element('1', 'Mempersiapkan kegiatan investigasi kecelakaan kerja', ['1.1' => 'Tingkat keparahan dan keseringan diidentifikasi sesuai kejadian.', '1.2' => 'Area/lokasi terjadinya kecelakaan diidentifikasi sesuai jenis kejadian.', '1.3' => 'Sarana dan prasarana investigasi diinventarisir sesuai jenis kejadian.', '1.4' => 'Personil dalam tim investigasi ditentukan sesuai peran dan tanggung jawab.', '1.5' => 'Dokumen yang terkait investigasi disiapkan sesuai kebutuhan investigasi.']),
                $this->element('2', 'Melaksanakan kegiatan investigasi kecelakaan kerja', ['2.1' => 'Lokasi kejadian diamankan sesuai dengan prosedur investigasi.', '2.2' => 'Kondisi kejadian akibat kecelakaan didokumentasikan sesuai kebutuhan investigasi.', '2.3' => 'Personil yang terkait kecelakaan ditentukan sesuai kejadian.', '2.4' => 'Pencarian penyebab dan sub penyebab kecelakaan dilakukan dengan metode 5 W dan 1 H.', '2.5' => 'Rekomendasi atau tindakan perbaikan dibuat sesuai hasil investigasi.']),
                $this->element('3', 'Melaporkan hasil kegiatan investigasi kecelakaan kerja', ['3.1' => 'Laporan hasil investigasi kecelakaan disusun sesuai format peraturan perundang-undangan yang berlaku.', '3.2' => 'Laporan hasil investigasi disampaikan ke pihak yang terkait.', '3.3' => 'Laporan hasil investigasi didokumentasikan sesuai prosedur.']),
            ]),
        ];
    }

    private function validationForm(): array
    {
        $validationAspects = ['Rencana asesmen', 'Interpretasi standar kompetensi', 'Interpretasi acuan pembanding lainnya', 'Proses asesmen', 'Penyeleksian dan penerapan metode asesmen', 'Penyeleksian dan penerapan perangkat asesmen', 'Bukti-bukti yang dikumpulkan', 'Pengambilan keputusan'];
        $aspectQuestions = [];
        foreach ($validationAspects as $index => $aspect) {
            $aspectQuestions[] = $this->q('VA_ASPEK_'.($index + 1), 'checkbox', $aspect, true, $this->options([
                'valid' => 'Aturan bukti - Valid', 'asli' => 'Aturan bukti - Asli', 'terkini' => 'Aturan bukti - Terkini', 'memadai' => 'Aturan bukti - Memadai',
                'validitas' => 'Prinsip asesmen - Validitas', 'reliabel' => 'Prinsip asesmen - Reliabel', 'fleksibel' => 'Prinsip asesmen - Fleksibel', 'adil' => 'Prinsip asesmen - Adil',
            ]));
        }
        return $this->form('FR.VA', 'Memberikan Kontribusi dalam Validasi Asesmen', 'pasca_asesmen', 'admin', 'admin', [
            $this->section('Tim dan Periode Validasi', [
                $this->q('VA_TANGGAL', 'date', 'Hari/tanggal validasi.', true), $this->q('VA_TEMPAT', 'short_text', 'Tempat validasi.', true),
                $this->q('VA_PERIODE', 'checkbox', 'Periode validasi.', true, $this->options(['sebelum' => 'Sebelum asesmen', 'saat' => 'Pada saat asesmen', 'setelah' => 'Setelah asesmen'])),
            ]),
            $this->section('Menyiapkan Proses Validasi', [
                $this->q('VA_TUJUAN', 'checkbox', 'Tujuan dan fokus validasi.', true, $this->options(['mutu' => 'Bagian proses penjaminan mutu organisasi', 'risiko' => 'Mengantisipasi risiko', 'bnsp' => 'Memenuhi persyaratan BNSP', 'bukti' => 'Memastikan kesesuaian bukti', 'praktik' => 'Meningkatkan kualitas asesmen', 'perangkat' => 'Mengevaluasi kualitas perangkat asesmen'])),
                $this->q('VA_KONTEKS', 'checkbox', 'Konteks validasi.', true, $this->options(['internal' => 'Internal organisasi', 'eksternal' => 'Eksternal organisasi', 'lisensi' => 'Proses lisensi/re-lisensi', 'kolega' => 'Dengan kolega asesor', 'organisasi_lain' => 'Kolega organisasi pelatihan atau asesmen'])),
                $this->q('VA_PENDEKATAN', 'checkbox', 'Pendekatan validasi.', true, $this->options(['panel' => 'Panel asesmen', 'moderasi' => 'Pertemuan moderasi', 'kaji_perangkat' => 'Mengkaji perangkat asesmen', 'acuan' => 'Acuan pembanding', 'uji_coba' => 'Pengujian lapangan/uji coba perangkat', 'umpan_balik' => 'Umpan balik klien', 'kaji_bukti' => 'Mengkaji bukti-bukti'])),
                $this->q('VA_ORANG_RELEVAN', 'checkbox', 'Orang yang relevan.', true, $this->options(['asesor' => 'Asesor kompetensi', 'lead' => 'Lead asesor/Ketua TUK', 'manager' => 'Manager/Supervisor', 'ahli' => 'Tenaga ahli', 'koordinator' => 'Koordinator pelatihan', 'asosiasi' => 'Anggota asosiasi industri/profesi'])),
                $this->q('VA_HASIL_DISKUSI', 'long_text', 'Hasil konfirmasi/diskusi tujuan, fokus, dan konteks.'),
                $this->q('VA_ACUAN', 'checkbox', 'Acuan pembanding, dokumen terkait, dan bahan.', true, $this->options(['standar' => 'Standar Kompetensi', 'skema' => 'Skema Sertifikasi', 'sop' => 'SOP/IK', 'manual' => 'Manual', 'kinerja' => 'Standar Kinerja', 'perangkat' => 'Perangkat Asesmen', 'peraturan' => 'Peraturan/Pedoman', 'bukti' => 'Bukti hasil asesmen'])),
            ]),
            $this->section('Kontribusi dalam Proses Validasi', array_merge([
                $this->q('VA_KOMUNIKASI', 'checkbox', 'Keterampilan komunikasi yang digunakan.', true, $this->options(['proaktif' => 'Proaktif', 'active_listening' => 'Active listening', 'empati' => 'Empati'])),
            ], $aspectQuestions)),
            $this->section('Hasil dan Implementasi', [
                $this->q('VA_TEMUAN_1', 'long_text', 'Temuan validasi 1.'), $this->q('VA_REKOMENDASI_1', 'long_text', 'Rekomendasi peningkatan praktik asesmen 1.'),
                $this->q('VA_TEMUAN_2', 'long_text', 'Temuan validasi 2.'), $this->q('VA_REKOMENDASI_2', 'long_text', 'Rekomendasi peningkatan praktik asesmen 2.'),
                $this->q('VA_RENCANA', 'long_text', 'Kegiatan perbaikan sesuai rekomendasi, waktu penyelesaian, dan penanggung jawab.', true),
            ]),
        ]);
    }

    private function unit(string $code, string $title, array $elements): array
    {
        return compact('code', 'title', 'elements');
    }

    private function element(string $code, string $name, array $criteria): array
    {
        return compact('code', 'name', 'criteria');
    }

    private function form(string $code, string $name, string $stage, string $filledBy, ?string $reviewedBy, array $sections, ?string $description = null): array
    {
        return compact('code', 'name', 'stage', 'sections') + [
            'filled_by' => $filledBy,
            'reviewed_by' => $reviewedBy,
            'description' => $description ?: 'Template digital '.self::SCHEME.' berdasarkan MASTER MUK-K3 TLM-2026.',
        ];
    }

    private function section(string $title, array $questions, ?string $description = null): array
    {
        return compact('title', 'description', 'questions');
    }

    private function q(string $code, string $type, string $label, bool $required = false, ?array $options = null, ?string $instructions = null, array $settings = []): array
    {
        return ['code' => $code, 'type' => $type, 'label' => $label, 'instructions' => $instructions, 'is_required' => $required, 'options' => $options, 'settings' => $settings];
    }

    private function oral(string $code, string $question, string $key, array $settings = []): array
    {
        return $this->q($code, 'oral_question', $question, true, null, 'Catat jawaban asesi secara singkat.', ['answer_key' => $key] + $settings);
    }

    private function signature(string $code, string $label, string $role): array
    {
        return $this->q($code, 'signature', $label, true, null, null, ['signer_role' => $role]);
    }

    private function adjustment(string $code, string $label, array $adjustmentOptions): array
    {
        return $this->q($code, 'radio', $label, true, $this->yesNo(), 'Jika Ya, pilih bentuk penyesuaian yang sesuai.', ['adjustment_options' => $adjustmentOptions]);
    }

    private function instrumentOptions(): array
    {
        return $this->options([
            'FR.IA.01' => 'FR.IA.01 Ceklis Observasi', 'FR.IA.02' => 'FR.IA.02 Tugas Praktik Demonstrasi',
            'FR.IA.03' => 'FR.IA.03 Pertanyaan Pendukung Observasi', 'FR.IA.04A' => 'FR.IA.04A Daftar Instruksi Terstruktur',
            'FR.IA.04B' => 'FR.IA.04B Penilaian Kegiatan Terstruktur', 'FR.IA.05' => 'FR.IA.05 Pertanyaan Tertulis Pilihan Ganda',
            'FR.IA.06' => 'FR.IA.06 Pertanyaan Tertulis Esai', 'FR.IA.07' => 'FR.IA.07 Pertanyaan Lisan',
            'FR.IA.08' => 'FR.IA.08 Verifikasi Portofolio', 'FR.IA.09' => 'FR.IA.09 Wawancara',
            'FR.IA.10' => 'FR.IA.10 Verifikasi Pihak Ketiga', 'FR.IA.11' => 'FR.IA.11 Reviu Produk',
        ]);
    }

    private function potentialOptions(): array
    {
        return $this->options(['1' => 'Pelatihan/pendidikan tertelusur standar kompetensi', '2' => 'Pelatihan/pendidikan belum berbasis kompetensi', '3' => 'Pekerja berpengalaman tertelusur standar kompetensi', '4' => 'Pekerja berpengalaman belum berbasis kompetensi', '5' => 'Belajar mandiri/otodidak']);
    }

    private function ak02UnitEvidenceQuestions(): array
    {
        $questions = [];
        $options = $this->options(['observasi' => 'Observasi demonstrasi', 'portofolio' => 'Portofolio', 'pihak_ketiga' => 'Pernyataan pihak ketiga', 'wawancara' => 'Pertanyaan wawancara', 'lisan' => 'Pertanyaan lisan', 'tertulis' => 'Pertanyaan tertulis', 'proyek' => 'Proyek kerja', 'lainnya' => 'Lainnya']);
        foreach ($this->competencyUnits() as $index => $unit) {
            $questions[] = $this->q('AK02_UNIT_'.($index + 1).'_BUKTI', 'checkbox', $unit['code'].' - '.$unit['title'], true, $options);
        }
        return $questions;
    }

    private function principleOptions(): array
    {
        return $this->options(['validitas' => 'Validitas', 'reliabel' => 'Reliabel', 'fleksibel' => 'Fleksibel', 'adil' => 'Adil']);
    }

    private function options(array $options): array
    {
        return collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all();
    }

    private function yesNo(): array
    {
        return $this->options(['ya' => 'Ya', 'tidak' => 'Tidak']);
    }

    private function achieved(): array
    {
        return $this->options(['tercapai' => 'Tercapai', 'belum_tercapai' => 'Belum tercapai']);
    }
}
