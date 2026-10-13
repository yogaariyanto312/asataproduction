import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    DoughnutController,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { Chart } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, LineElement, LineController, PointElement, ArcElement, DoughnutController, Tooltip);

/**
 * Chart.js (±200 KB) dipisah dari halaman Dashboard dan dimuat lewat
 * React.lazy: kartu-kartu lain sudah tampil duluan, grafik menyusul. Di HP
 * grafik berada di bawah lipatan layar, jadi tidak perlu menahan tampilan awal.
 */
export default function GrafikChart(props) {
    return <Chart {...props} />;
}
