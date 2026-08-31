"use client";

import { TrendingUp } from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type { AttendanceTrendPoint } from "@/hooks/use-attendance-stats";

function TrendTooltip({
  active,
  payload,
}: {
  active?: boolean;
  payload?: { payload: AttendanceTrendPoint }[];
}) {
  if (!active || !payload?.length) return null;
  const point = payload[0].payload;

  return (
    <div className="rounded-xl border border-line bg-popover px-3 py-2 shadow-lg">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-300">
        Semaine {point.semaine}
      </p>
      <p className="font-display text-base font-bold text-ink-900">{point.taux}%</p>
    </div>
  );
}

export function AttendanceTrendChart({ data }: { data: AttendanceTrendPoint[] }) {
  if (data.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line py-10 text-center">
        <TrendingUp className="h-5 w-5 text-ink-300" />
        <p className="text-sm text-ink-500">
          Pas encore assez de séances pour tracer une tendance.
        </p>
      </div>
    );
  }

  const average = Math.round(data.reduce((sum, p) => sum + p.taux, 0) / data.length);

  return (
    <div className="relative overflow-hidden rounded-2xl border border-line bg-card p-5">
      <div
        aria-hidden
        className="pointer-events-none absolute -right-10 -top-16 h-40 w-40 rounded-full opacity-40 blur-3xl"
        style={{
          background:
            "radial-gradient(circle, var(--indigo-500) 0%, transparent 70%)",
        }}
      />

      <div className="relative mb-1 flex items-baseline justify-between">
        <div>
          <h3 className="font-display text-[15px] font-bold tracking-tight text-ink-900">
            Ma progression
          </h3>
          <p className="text-xs text-ink-500">Taux de présence, 8 dernières semaines</p>
        </div>
        <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold text-indigo-600">
          moy. {average}%
        </span>
      </div>

      <div className="relative h-[168px] w-full">
        <ResponsiveContainer width="100%" height="100%" debounce={0}>
          <AreaChart data={data} margin={{ top: 16, right: 8, bottom: 0, left: 0 }}>
            <defs>
              <linearGradient id="attendanceTrendFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--indigo-600)" stopOpacity={0.32} />
                <stop offset="100%" stopColor="var(--indigo-600)" stopOpacity={0} />
              </linearGradient>
            </defs>
            <CartesianGrid vertical={false} stroke="var(--line)" strokeDasharray="3 5" />
            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={false}
              tick={{ fill: "var(--ink-300)", fontSize: 11, fontWeight: 600 }}
              dy={8}
            />
            <YAxis hide domain={[0, 100]} />
            <Tooltip
              content={<TrendTooltip />}
              cursor={{ stroke: "var(--indigo-500)", strokeWidth: 1, strokeDasharray: "3 3" }}
            />
            <Area
              type="monotone"
              dataKey="taux"
              stroke="var(--indigo-600)"
              strokeWidth={2.5}
              fill="url(#attendanceTrendFill)"
              dot={{ r: 3, fill: "var(--indigo-600)", strokeWidth: 2, stroke: "var(--card)" }}
              activeDot={{ r: 5, fill: "var(--indigo-600)", strokeWidth: 2, stroke: "var(--card)" }}
              animationDuration={900}
              animationEasing="ease-out"
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
