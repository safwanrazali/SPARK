        {{-- 1 · Maklumat laporan --}}
        <div class="report-card mb-4" data-seksyen="maklumat">
            <h4 class="section-title">1 · Maklumat Laporan</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tarikh Laporan</label>
                    <input type="date" name="tarikh_laporan" class="form-control"
                        value="{{ old('tarikh_laporan', $borang['tarikh_laporan'] ?? null) }}">
                </div>
                <div class="col-md-4 mb-3">
                    {{-- Format kod rujukan dan senarai status diambil daripada
                         config/kriptografi.php — sumber yang SAMA digunakan oleh
                         pengesahan dalam AnalisisInventoriController@simpan. Jangan
                         tulis semula nilainya di sini supaya borang dan pengesahan
                         tidak terpesong. --}}
                    <label class="form-label" for="kod_rujukan">Kod Rujukan Laporan</label>
                    <input type="text" id="kod_rujukan" name="kod_rujukan"
                        class="form-control @error('kod_rujukan') is-invalid @enderror"
                        placeholder="cth. {{ config('kriptografi.kod_rujukan.contoh') }}"
                        pattern="{{ config('kriptografi.kod_rujukan.corak') }}"
                        value="{{ old('kod_rujukan', $borang['kod_rujukan'] ?? null) }}">
                    <div class="form-text">
                        Format: {{ config('kriptografi.kod_rujukan.format') }}
                    </div>
                    @error('kod_rujukan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status_laporan">Status Laporan</label>
                    <select id="status_laporan" name="status_laporan"
                        class="form-select @error('status_laporan') is-invalid @enderror">
                        @foreach (config('kriptografi.status_laporan') as $status)
                            <option value="{{ $status }}" @selected(old('status_laporan', $borang['status_laporan'] ?? null) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                    @error('status_laporan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
