import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatCurrency } from '@/utils/format';

export interface PacProjectionPoint {
    month: string;
    label: string;
    contributions: number;
    cumulative: number;
}

interface PacProjectionChartProps {
    series: PacProjectionPoint[];
    currencyCode?: string;
}

export default function PacProjectionChart({ series, currencyCode = 'EUR' }: PacProjectionChartProps) {
    if (series.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nessun PAC attivo. Crea un piano di accumulo per vedere la proiezione dei versamenti.
            </p>
        );
    }

    return (
        <div className="h-56 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={series} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-gray-200 dark:stroke-gray-700" />
                    <XAxis dataKey="label" tick={{ fontSize: 11 }} interval="preserveStartEnd" />
                    <YAxis
                        tick={{ fontSize: 11 }}
                        tickFormatter={(value) => formatCurrency(Number(value), currencyCode)}
                        width={72}
                    />
                    <Tooltip
                        formatter={(value, name) => [
                            formatCurrency(Number(value ?? 0), currencyCode),
                            name === 'cumulative' ? 'Totale versamenti' : 'Versamento mese',
                        ]}
                        labelFormatter={(label) => String(label)}
                    />
                    <Area
                        type="monotone"
                        dataKey="cumulative"
                        name="cumulative"
                        stroke="#10b981"
                        fill="#10b98133"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
