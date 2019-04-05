
import React from "react";
import PropTypes from "prop-types";

import { scaleTime } from "d3-scale";
import { curveMonotoneX } from "d3-shape";

import { ChartCanvas, Chart } from "react-stockcharts";
import { AreaSeries } from "react-stockcharts/lib/series";
import { XAxis, YAxis } from "react-stockcharts/lib/axes";
import { fitWidth } from "react-stockcharts/lib/helper";
import { createVerticalLinearGradient, hexToRGBA } from "react-stockcharts/lib/utils";

let config_data = JSON.stringify(stockinfo_config);
let config_content = JSON.parse(config_data);

const stop_1_color = "#b5d0ff";
const stop_2_color = "#6fa4fc";
const stop_3_color = "#4286f4";
const stroke_color = config_content.stroke_color;

const canvasGradient = createVerticalLinearGradient([
  { stop: 0, color: hexToRGBA(stop_1_color, 0.2) },
  { stop: 0.7, color: hexToRGBA(stop_2_color, 0.4) },
  { stop: 1, color: hexToRGBA(stop_3_color, 0.8) },
]);

const currentDate = new Date();

const currentYear = currentDate.getFullYear();
const currentMonth = currentDate.getMonth();
const currentDay = currentDate.getDay();
const priorDate = new Date(new Date().setDate(currentDate.getDate()-30));

const priorYear = priorDate.getFullYear();
const priorMonth = priorDate.getMonth();
const priorDay = priorDate.getDay();

class AreaChart extends React.Component {
  render() {
    const { data, type, width, ratio, series, name, symbol } = this.props;
    return (
      <ChartCanvas ratio={ratio} width={width} height={400}
                   margin={{ left: 50, right: 50, top: 10, bottom: 30 }}
                   seriesName={series}
                   data={data}
                   type={type}
                   xAccessor={d => d.date}
                   xScale={scaleTime()}
                   mouseMoveEvent={true}
                   panEvent={true}
                   zoomEvent={true}
                   clamp={false}
                   xExtents={[new Date(priorYear, priorMonth, priorDay), new Date(currentYear, currentMonth, currentDay)]}
      >
        <Chart id={0} yExtents={d => d.close}>
          <defs>
            <linearGradient id="StockGradient" x1="0" y1="100%" x2="0" y2="0%">
              <stop offset="0%" stopColor={stop_1_color} stopOpacity={0.2} />
              <stop offset="70%" stopColor={stop_1_color} stopOpacity={0.4} />
              <stop offset="100%"  stopColor={stop_1_color} stopOpacity={0.8} />
            </linearGradient>
          </defs>
          <XAxis axisAt="bottom" orient="bottom" ticks={6}/>
          <YAxis axisAt="left" orient="left" />
          <AreaSeries
            yAccessor={d => d.close}
            fill="url(#StockGradient)"
            strokeWidth={2}
            stroke={stroke_color}
            interpolation={curveMonotoneX}
            canvasGradient={canvasGradient}
          />
        </Chart>
      </ChartCanvas>
    );
  }
}


AreaChart.propTypes = {
  data: PropTypes.array.isRequired,
  width: PropTypes.number.isRequired,
  ratio: PropTypes.number.isRequired,
  type: PropTypes.oneOf(["svg", "hybrid"]).isRequired,
  // type: PropTypes.string.isRequired,
  series: PropTypes.string.isRequired,
};

AreaChart.defaultProps = {
  type: "svg",
  series: "DJIA"
};
AreaChart = fitWidth(AreaChart);

export default AreaChart;
