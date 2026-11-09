'use strict';

/* Chart.js docs: https://www.chartjs.org/ */

window.chartColors = {
	blue: '#3A7BD5',
	gray: '#a9b5c9',
	text: '#252930',
	border: '#E9EAF0'
};

const monthArray = [];
const monthIdArray = [];
const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const currentDate = new Date();
for (let i = 0; i < 6; i++) {
	// Create a new date object for the previous months
	const prevDate = new Date(currentDate);
	prevDate.setMonth(currentDate.getMonth() - i);
	monthIdArray.push(`${prevDate.getMonth() + 1}`);
	monthArray.push(`${monthNames[prevDate.getMonth()]}`);
}

const monthData = [];
fetch("api/analytics.php", {
	method: "POST",
	headers: {
		"Content-Type": "application/json",
	},
	body: JSON.stringify({
		monthIdArray: monthIdArray,
	}),
})
	.then((response) => response.text())
	.then((data) => {
		console.log(data);
		const parsedData = JSON.parse(data);
		monthData.push(...parsedData);
		monthData.reverse();
	})
	.catch((error) => {
		console.log("Error:", error);
	});

const monthDataAll = [];
fetch("api/analyticsAll.php", {
	method: "POST",
	headers: {
		"Content-Type": "application/json",
	},
	body: JSON.stringify({
		monthIdArray: monthIdArray,
	}),
})
	.then((response) => response.text())
	.then((data) => {
		console.log(data);
		const parsedData = JSON.parse(data);
		// Assign the parsed data to monthData
		monthDataAll.push(...parsedData); // Assuming data is an array
		monthDataAll.reverse();
	})
	.catch((error) => {
		console.log("Error:", error);
	});
/* Random number generator for demo purpose */
var randomDataPoint = function () { return Math.round(Math.random() * 10) };


//Chart.js Line Chart Example 

var lineChartConfig = {
	type: 'line',

	data: {
		labels: monthArray.slice().reverse(),

		datasets: [{
			label: 'All Inquiries',
			fill: false,
			backgroundColor: window.chartColors.blue,
			borderColor: window.chartColors.blue,
			data: monthDataAll,
		}, {
			label: 'Registration Completed',
			borderDash: [3, 5],
			backgroundColor: window.chartColors.gray,
			borderColor: window.chartColors.gray,

			data: monthData,
			fill: false,
		}]
	},
	options: {
		responsive: true,
		aspectRatio: 1.5,

		legend: {
			display: true,
			position: 'bottom',
			align: 'end',
		},

		title: {
			display: false,
			text: 'Chart.js Line Chart Example',

		},
		tooltips: {
			mode: 'index',
			intersect: false,
			titleMarginBottom: 10,
			bodySpacing: 10,
			xPadding: 16,
			yPadding: 16,
			borderColor: window.chartColors.border,
			borderWidth: 1,
			backgroundColor: '#fff',
			bodyFontColor: window.chartColors.text,
			titleFontColor: window.chartColors.text,

			callbacks: {
				//Ref: https://stackoverflow.com/questions/38800226/chart-js-add-commas-to-tooltip-and-y-axis
				label: function (tooltipItem, data) {
					if (parseInt(tooltipItem.value) >= 1000) {
						return tooltipItem.value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
					} else {
						return tooltipItem.value;
					}
				}
			},

		},
		hover: {
			mode: 'nearest',
			intersect: true
		},
		scales: {
			xAxes: [{
				display: true,
				gridLines: {
					drawBorder: false,
					color: window.chartColors.border,
				},
				scaleLabel: {
					display: false,

				}
			}],
			yAxes: [{
				display: true,
				gridLines: {
					drawBorder: false,
					color: window.chartColors.border,
				},
				scaleLabel: {
					display: false,
				},
				ticks: {
					beginAtZero: true,
					userCallback: function (value, index, values) {
						return value.toLocaleString();   //Ref: https://stackoverflow.com/questions/38800226/chart-js-add-commas-to-tooltip-and-y-axis
					}
				},
			}]
		}
	}
};



// Chart.js Bar Chart Example 

var barChartConfig = {
	type: 'bar',

	data: {
		labels: monthArray.slice().reverse(),
		datasets: [{
			label: 'Number of Students',
			backgroundColor: window.chartColors.blue,
			borderColor: window.chartColors.blue,
			borderWidth: 1,
			maxBarThickness: 16,
			data: monthData
		}]
	},
	options: {
		responsive: true,
		aspectRatio: 1.5,
		legend: {
			position: 'bottom',
			align: 'end',
		},
		title: {
			display: false,
			text: 'Chart.js Bar Chart Example'
		},
		tooltips: {
			mode: 'index',
			intersect: false,
			titleMarginBottom: 10,
			bodySpacing: 10,
			xPadding: 16,
			yPadding: 16,
			borderColor: window.chartColors.border,
			borderWidth: 1,
			backgroundColor: '#fff',
			bodyFontColor: window.chartColors.text,
			titleFontColor: window.chartColors.text,

		},
		scales: {
			xAxes: [{
				display: true,
				gridLines: {
					drawBorder: false,
					color: window.chartColors.border,
				},

			}],
			yAxes: [{
				display: true,
				gridLines: {
					drawBorder: false,
					color: window.chartColors.borders,
				},


			}]
		}

	}
}







// Generate charts on load
window.addEventListener('load', function () {

	var lineChart = document.getElementById('canvas-linechart').getContext('2d');
	window.myLine = new Chart(lineChart, lineChartConfig);

	var barChart = document.getElementById('canvas-barchart').getContext('2d');
	window.myBar = new Chart(barChart, barChartConfig);


});

