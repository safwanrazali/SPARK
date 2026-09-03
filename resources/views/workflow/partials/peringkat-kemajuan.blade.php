{{-- `use` MESTI diulang dalam setiap partial: @include dikompil menjadi
     fail PHP tersendiri, jadi import templat induk TIDAK diwarisi. --}}
@php
    use App\Support\AliranKerja;
@endphp

    {{--
        Satu tempat sahaja untuk kedudukan DAN tindakan. Stepper mendatar
        memaparkan lima peringkat utama beserta sub-peringkatnya, dan bar di
        bawahnya membawa tindakan yang benar-benar terbuka kepada peranan
        pengguna. Siapa menyelesaikan apa dan bila kekal direkodkan dalam
        "Sejarah Peringkat" di hujung halaman.
    --}}
    <div class="report-card mb-4">

        <h4 class="section-title">Peringkat Kemajuan</h4>

        <x-workflow-stepper :workflow="$workflow" :peringkat="$peringkat" />

        @if ($adaTindakan)
            <div class="peringkat-tindakan">

                <p class="peringkat-tindakan__tajuk">
                    Tindakan yang tidak dibenarkan bagi peranan anda tidak dipaparkan.
                </p>

                {{--
                    Satu blok bagi setiap peringkat, dijana daripada takrifan
                    aliran kerja. Medan yang ditangkap, labelnya dan siapa
                    memasukkan No. Rujukan semuanya datang daripada
                    App\Support\AliranKerja — jadi menambah medan pada satu
                    peringkat ialah satu perubahan pada takrifan, bukan pada
                    paparan ini.
                --}}
                @foreach ($peringkatBertindak as $kunci)
                    @php
                        $rekod = $peringkat->get($kunci);
                        $medan = AliranKerja::medan($kunci);
                        $labelRujukan = AliranKerja::labelRujukan($kunci);
                        $milikSaya = $bolehKendali($kunci);
                    @endphp
                    @include('workflow.partials.peringkat-tindakan-item')
                @endforeach

            </div>
        @endif

    </div>
