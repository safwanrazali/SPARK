        {{-- 2 · Status data diterima --}}
        <div class="report-card mb-4" data-seksyen="data_status">
            <h4 class="section-title">2 · Status Data Diterima (Jadual 0–2)</h4>

            @foreach (['j0' => 'Jadual 0 : Inventori', 'j1' => 'Jadual 1 : SBOM', 'j2' => 'Jadual 2 : CBOM'] as $kunci => $nama)
                @php $sedia = $data['data_status'][$kunci] ?? []; @endphp
                {{-- SATU status sahaja bagi setiap jadual. Medan "Penerimaan"
                     yang terdahulu telah dibuang: selepas kebolehgunaan
                     diselaraskan kepada Lengkap/Tidak Lengkap, kedua-duanya
                     menanyakan soalan yang sama, dan hanya kebolehgunaan yang
                     dipaparkan dalam laporan. --}}
                <div class="row align-items-end mb-2">
                    <div class="col-md-5"><strong>{{ $nama }}</strong></div>
                    <div class="col-md-4">
                        <label class="form-label">Status Kebolehgunaan</label>
                        <select name="data_status[{{ $kunci }}][kebolehgunaan]" class="form-select">
                            @foreach (config('kriptografi.kebolehgunaan_data') as $k)
                                <option @selected(($sedia['kebolehgunaan'] ?? config('kriptografi.kebolehgunaan_data')[0]) === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Penerangan boleh berbilang: templat laporan memaparkannya
                     sebagai senarai bernombor (i, ii, iii) bagi setiap jadual.
                     Nilai lama yang disimpan sebagai satu rentetan dinormalkan
                     oleh BorangAnalisis::senaraiTeks(), jadi rekod sedia ada
                     terus dimuatkan sebagai satu baris. --}}
                @php $penerangan = \App\Support\BorangAnalisis::senaraiTeks($sedia['nota'] ?? null) ?: ['']; @endphp
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Penerangan Status</label>
                        <div class="penerangan-senarai" data-penerangan="{{ $kunci }}">
                            @foreach ($penerangan as $titik)
                                <div class="input-group mb-2 penerangan-baris">
                                    <input type="text" name="data_status[{{ $kunci }}][nota][]"
                                        class="form-control" value="{{ $titik }}"
                                        placeholder="cth. Medan kategori aset tidak diisi sepenuhnya.">
                                    <button type="button" class="btn btn-outline-light penerangan-buang"
                                        aria-label="Buang penerangan ini">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah"
                            data-sasaran="{{ $kunci }}">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Penerangan
                        </button>
                    </div>
                </div>
            @endforeach

            {{-- Fail rujukan yang menjadi sumber analisis. Direkodkan di sini,
                 BUKAN diambil daripada modul muat naik: spesifikasi bahagian 3
                 menetapkan aliran pelaporan tidak bergantung pada modul itu,
                 dan sistem tidak mewajibkan sebarang muat naik dokumen. --}}
            @php $failSumber = \App\Support\BorangAnalisis::senaraiTeks($data['fail_sumber'] ?? null) ?: ['']; @endphp
            <div class="mt-3">
                <label class="form-label">Fail Sumber (dipaparkan di bawah "Catatan:" dalam laporan)</label>
                <div class="penerangan-senarai" data-penerangan="fail_sumber">
                    @foreach ($failSumber as $fail)
                        <div class="input-group mb-2 penerangan-baris">
                            <input type="text" name="fail_sumber[]" class="form-control" value="{{ $fail }}"
                                placeholder="cth. LAMPIRAN A – BUKU KERJA PELAKSANAAN MIGRASI PQC">
                            <button type="button" class="btn btn-outline-light penerangan-buang"
                                aria-label="Buang fail sumber ini">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-light penerangan-tambah" data-sasaran="fail_sumber">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Fail Sumber
                </button>
            </div>
        </div>
