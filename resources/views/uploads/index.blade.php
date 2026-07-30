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
            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">
                        Pilih Sektor
                    </label>
                    <select id="sector-select" name="sector_code" class="form-select" required>

                        <option value="">
                            -- Sila Pilih --
                        </option>

                        @foreach (config('sektor') as $sectorCode => $sector)
                            <option value="{{ $sectorCode }}" data-name="{{ $sector['name'] }}">
                                {{ $sector['name'] }}
                            </option>
                        @endforeach

                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label">Kod Sektor</label>
                    <input id="sector-display-code" type="text" class="form-control" readonly>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">
                        Nama Agensi / Entiti
                    </label>
                    <select id="agency-select" name="agency_code" class="form-select" required disabled>
                        <option value="">
                            -- Pilih Sektor Dahulu --
                        </option>
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label">Kod Agensi</label>
                    <input id="agency-display-code" type="text" class="form-control" readonly>
                </div>
            </div>

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
            const sectorCodeField = document.getElementById('sector-display-code');
            const agencyCodeField = document.getElementById('agency-display-code');
            const agencyNameField = document.getElementById('agency-display-name');
            const sectorNameInput = document.getElementById('sector-name-input');
            const agencyNameInput = document.getElementById('agency-name-input');

            function resetAgencyFields() {
                agencySelect.innerHTML = '<option value="">-- Pilih Sektor Dahulu --</option>';
                agencySelect.disabled = true;
                agencyCodeField.value = '';
                agencyNameField.value = '';
                agencyNameInput.value = '';
            }

            function updateSectorFields() {
                const selectedCode = sectorSelect.value;
                const sector = sektorConfig[selectedCode] ?? null;

                sectorCodeField.value = sector ? selectedCode : '';
                sectorNameInput.value = sector ? sector.name : '';

                if (!sector || !sector.agencies.length) {
                    return resetAgencyFields();
                }

                agencySelect.disabled = false;
                agencySelect.innerHTML = '<option value="">-- Sila Pilih --</option>';

                sector.agencies.forEach((agency) => {
                    const option = document.createElement('option');
                    option.value = agency.code;
                    option.textContent = agency.name;
                    if (agency.code.startsWith('K')) {
                        option.style.color = 'purple';
                    }
                    agencySelect.appendChild(option);
                });
            }

            function updateAgencyFields() {
                const selectedSector = sektorConfig[sectorSelect.value] ?? null;
                const selectedAgencyCode = agencySelect.value;
                const selectedAgency = selectedSector ?
                    selectedSector.agencies.find((agency) => agency.code === selectedAgencyCode) :
                    null;

                agencyCodeField.value = selectedAgency ? selectedAgency.code : '';
                agencyNameField.value = selectedAgency ? selectedAgency.name : '';
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
