<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Peraturan pengesahan bagi medan satu peringkat aliran kerja.
 *
 * Dipisahkan daripada KemajuanAnalisisController kerana peraturannya
 * DITERBITKAN sepenuhnya daripada App\Support\AliranKerja: menambah medan
 * pada satu peringkat sepatutnya mengemas kini pengesahannya secara
 * automatik, tanpa suntingan kedua dalam lapisan HTTP.
 *
 * Rakan kepada App\Support\SyaratPeringkat — kelas itu menjawab "medan mana
 * yang masih tiada", kelas ini menjawab "nilai apa yang diterima".
 *
 * TIADA peraturan berubah semasa pemisahan.
 */
final class PeraturanPeringkat
{
    /**
     * Peraturan pengesahan bagi medan peringkat ini.
     *
     * Hanya medan yang ditakrifkan bagi peringkat berkenaan diterima; borang
     * tidak boleh menulis medan peringkat lain walaupun ia dihantar.
     *
     * `status_borang` disahkan terhadap perbendaharaan rasminya
     * (AliranKerja::STATUS_BORANG), jadi nilai di luar senarai itu ditolak
     * walaupun borang dihantar terus tanpa melalui antara muka.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function medan(string $stage): array
    {
        $peraturan = [];

        foreach (array_keys(AliranKerja::medan($stage)) as $medan) {
            $peraturan[$medan] = match (true) {
                in_array($medan, AliranKerja::MEDAN_TARIKH, true) => ['nullable', 'date'],
                $medan === AliranKerja::MEDAN_STATUS_BORANG => [
                    'nullable',
                    Rule::in(AliranKerja::statusBorang($stage)),
                ],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        // Tarikh Tamat tidak boleh mendahului Tarikh Mula — satu-satunya
        // peraturan silang medan, dan ia datang daripada makna medan itu
        // sendiri, bukan daripada proses perniagaan yang belum ditetapkan.
        if (isset($peraturan[AliranKerja::MEDAN_TARIKH_TAMAT], $peraturan[AliranKerja::MEDAN_TARIKH_MULA])) {
            $peraturan[AliranKerja::MEDAN_TARIKH_TAMAT][] = 'after_or_equal:'.AliranKerja::MEDAN_TARIKH_MULA;
        }

        return $peraturan;
    }

    /**
     * Label medan untuk mesej ralat — diambil daripada takrifan aliran kerja
     * supaya borang dan mesej ralat menggunakan perkataan yang sama.
     *
     * @return array<string, string>
     */
    public static function nama(string $stage): array
    {
        return AliranKerja::medan($stage);
    }
}
