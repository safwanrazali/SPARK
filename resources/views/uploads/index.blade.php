@extends('layouts.app')

@section('title', 'Muat Naik Data')

@section('page-title', 'Muat Naik Data')

@section('content')

    <div class="report-card">

        <h4 class="section-title">
            Import Data Excel
        </h4>

        <p class="text-secondary">
            Muat naik fail Excel yang mengandungi helaian
            MasterTable atau MasterTable_Risk.
        </p>

        <form action="{{ route('muat-naik.preview') }}" method="POST" enctype="multipart/form-data">

            @csrf
            {{-- Sektor dan entiti dikenali melalui kod sahaja; medan kod
                 baca-sahaja yang dahulu berpasangan dengan nama telah dibuang
                 kerana ia kini hanya menduakan pilihan itu sendiri. --}}
            <div class="row mb-3">
                <div class="col">
                    <label class="form-label" for="sector-select">
                        Kod Sektor
                    </label>
                    <select id="sector-select" name="sector_code" class="form-select" required>

                        <option value="">
                            -- Sila Pilih --
                        </option>

                        @foreach (config('sektor') as $sectorCode => $sector)
                            <option value="{{ $sectorCode }}">{{ $sectorCode }}</option>
                        @endforeach

                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label" for="agency-select">
                        Kod Agensi / Entiti
                    </label>
                    <select id="agency-select" name="agency_code" class="form-select" required disabled>
                        <option value="">
                            -- Pilih Sektor Dahulu --
                        </option>
                    </select>
                </div>
            </div>

            {{-- Nama tidak dipapar, tetapi masih dihantar supaya rekod yang
                 disimpan kekal lengkap. --}}
            <input type="hidden" name="sector_name" id="sector-name-input">
            <input type="hidden" name="agency_name" id="agency-name-input">

            <div class="mb-3">

                <label class="form-label">
                    Pilih Fail Excel
                </label>

                <input type="file" name="fail_excel" class="form-control" required>
                @error('fail_excel')
                    <div class="text-danger mt-2">

                        {{ $message }}

                    </div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Muat Naik
            </button>

        </form>

        <script>
            const sektorConfig = @json(config('sektor'));
            const sectorSelect = document.getElementById('sector-select');
            const agencySelect = document.getElementById('agency-select');
            const sectorNameInput = document.getElementById('sector-name-input');
            const agencyNameInput = document.getElementById('agency-name-input');

            function resetAgencyFields() {
                agencySelect.innerHTML = '<option value="">-- Pilih Sektor Dahulu --</option>';
                agencySelect.disabled = true;
                agencyNameInput.value = '';
            }

            function updateSectorFields() {
                const sector = sektorConfig[sectorSelect.value] ?? null;

                // Nama tidak dipapar tetapi tetap dihantar bersama borang.
                sectorNameInput.value = sector ? sector.name : '';
                agencyNameInput.value = '';

                if (!sector || !sector.agencies.length) {
                    return resetAgencyFields();
                }

                agencySelect.disabled = false;
                agencySelect.innerHTML = '<option value="">-- Sila Pilih --</option>';

                sector.agencies.forEach((agency) => {
                    const option = document.createElement('option');
                    option.value = agency.code;
                    option.textContent = agency.code;
                    if (agency.code.startsWith('K')) {
                        // Gaya dalam resources/scss/forms.scss (option.is-kementerian).
                        option.classList.add('is-kementerian');
                    }
                    agencySelect.appendChild(option);
                });
            }

            function updateAgencyFields() {
                const selectedSector = sektorConfig[sectorSelect.value] ?? null;
                const selectedAgency = selectedSector ?
                    selectedSector.agencies.find((agency) => agency.code === agencySelect.value) :
                    null;

                agencyNameInput.value = selectedAgency ? selectedAgency.name : '';
            }

            sectorSelect.addEventListener('change', () => {
                updateSectorFields();
            });

            agencySelect.addEventListener('change', () => {
                updateAgencyFields();
            });
        </script>

    </div>
    <div class="report-card mt-4">

        <h5>Keperluan Fail</h5>

        <ul>

            <li>Format .xlsx atau .xls</li>

            <li>
                Mesti mengandungi helaian
                MasterTable atau MasterTable_Risk
            </li>

            <li>
                Struktur kolum hendaklah mengikut
                templat rasmi organisasi
            </li>

        </ul>

    </div>
@endsection
