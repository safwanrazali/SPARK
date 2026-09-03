    <script>
        // Papar / sembunyi medan bilangan algoritma.
        // Kelas .is-hidden (resources/scss/states.scss) ialah keadaan yang sama
        // yang dipaparkan oleh Blade melalui @class, jadi togol di sini
        // menyambung terus daripada keadaan awal pelayan.
        document.querySelectorAll('.algo-toggle').forEach(cb => {
            cb.addEventListener('change', function() {
                document.querySelectorAll('.algo-medan-' + this.dataset.target)
                    .forEach(el => el.classList.toggle('is-hidden', !this.checked));
            });
        });

        // Baris dinamik protokol / pustaka / vendor.
        document.querySelectorAll('.tambah-baris').forEach(btn => {
            btn.addEventListener('click', function() {
                const medan = this.dataset.medan;
                const senarai = document.getElementById('senarai-' + medan);
                const templat = document.getElementById('templat-' + medan);
                const indeks = senarai.querySelectorAll('.baris-item').length + Date.now() % 1000;
                const klon = templat.content.cloneNode(true);

                klon.querySelectorAll('[data-nama]').forEach(input => {
                    input.name = `${medan}[${indeks}][${input.dataset.nama}]`;
                });

                senarai.appendChild(klon);
                this.closest('[data-senarai]').querySelector('.nota-kosong')
                    .classList.add('is-hidden');
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.padam-baris')) {
                e.target.closest('.baris-item').remove();
            }
        });

        // ── FASA 6 — draf: jejak seksyen, elak kehilangan data, autosimpan ──
        (function() {
            const borang = document.getElementById('borang-analisis');
            const status = document.getElementById('draft-status');
            const medanSeksyen = document.getElementById('seksyen-semasa');

            if (!borang) return;

            let kotor = false; // ada perubahan belum disimpan
            let menghantar = false; // borang sedang dihantar

            // Seksyen terakhir yang disentuh pengguna disimpan bersama draf.
            borang.addEventListener('focusin', function(e) {
                const kad = e.target.closest('[data-seksyen]');
                if (kad) medanSeksyen.value = kad.dataset.seksyen;
            });

            borang.addEventListener('input', () => kotor = true);
            borang.addEventListener('change', () => kotor = true);
            borang.addEventListener('submit', () => menghantar = true);

            // Amaran sebelum meninggalkan halaman dengan kerja belum disimpan.
            window.addEventListener('beforeunload', function(e) {
                if (!kotor || menghantar) return;
                e.preventDefault();
                e.returnValue = '';
            });

            // Autosimpan draf secara senyap. Tidak mengganggu paparan dan
            // hanya berjalan apabila ada perubahan sebenar.
            const SELANG = 180000; // 3 minit

            async function autosimpan() {
                if (!kotor || menghantar) return;

                try {
                    const jawapan = await fetch('{{ route('analisis.draf') }}', {
                        method: 'POST',
                        body: new FormData(borang),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!jawapan.ok) return;

                    const hasil = await jawapan.json();
                    kotor = false;

                    if (status) {
                        status.textContent = 'Draf disimpan automatik pada ' + hasil.disimpan_pada + '.';
                    }
                } catch (e) {
                    // Kegagalan rangkaian dibiarkan senyap; amaran beforeunload
                    // kekal melindungi kerja pengguna.
                }
            }

            setInterval(autosimpan, SELANG);
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') autosimpan();
            });
        })();
    </script>
