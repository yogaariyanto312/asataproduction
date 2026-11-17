import '../../css/trafo.css';

/*
 * Maskot halaman masuk: trafo dengan petir yang bereaksi (PRD 12, pengganti robot).
 * state: 'idle' | 'typing' | 'secret' | 'proses' | 'ok' | 'error' | 'kunci'
 * tingkat: 0..1, makin panjang isian makin terang petirnya (hanya saat 'typing').
 */
export default function TrafoMaskot({ state = 'idle', tingkat = 0 }) {
    return (
        <div className="lg-trafo" data-state={state} style={{ '--lg-i': tingkat }} aria-hidden="true">
            <span className="lg-trafo-cincin" />
            <svg className="lg-trafo-svg" viewBox="0 0 160 140" fill="none">
                <ellipse className="lg-trafo-bayang" cx="80" cy="128" rx="48" ry="6" />

                <g className="lg-trafo-sirip">
                    <rect x="24" y="64" width="7" height="44" rx="3.5" />
                    <rect x="33" y="60" width="7" height="52" rx="3.5" />
                    <rect x="120" y="60" width="7" height="52" rx="3.5" />
                    <rect x="129" y="64" width="7" height="44" rx="3.5" />
                </g>

                <rect className="lg-trafo-badan" x="40" y="52" width="80" height="66" rx="16" />
                <path className="lg-trafo-garis" d="M50 67h60" />

                <g className="lg-trafo-isolator">
                    <rect x="56" y="34" width="12" height="20" rx="3" />
                    <rect x="53" y="29" width="18" height="8" rx="3.5" />
                    <rect x="92" y="34" width="12" height="20" rx="3" />
                    <rect x="89" y="29" width="18" height="8" rx="3.5" />
                </g>

                <path className="lg-trafo-busur" d="M62 27C70 6 90 6 98 27" />
                <g className="lg-trafo-percik">
                    <circle cx="80" cy="12" r="2" />
                    <circle cx="68" cy="16" r="1.5" />
                    <circle cx="92" cy="16" r="1.5" />
                </g>

                <g className="lg-trafo-petir">
                    <path d="M85 68 69 93h12l-6 19 23-29H86l6-15Z" />
                </g>

                <g className="lg-trafo-gembok">
                    <rect x="68" y="88" width="24" height="18" rx="4" />
                    <path d="M73 88v-6a7 7 0 0 1 14 0v6" />
                </g>

                <path className="lg-trafo-centang" d="m66 90 11 11 19-23" />
            </svg>
        </div>
    );
}
