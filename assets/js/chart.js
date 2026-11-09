// Color Varables
const chartGreen = '#48CD50';
const chartRed = '#D85E44';
const chart_color_1 = '#3A7BD5';
const chart_color_2 = '#5C9BFF';
const chart_color_3 = '#8FB9FF';
const chart_color_4 = '#C7D8EE';
const chartOrange = '#FF9500';
const chartPink = '#fd53bc';
const chartYellow = '#e0d317';
const chartPurple = '#AF52DE';
const chartGreenBlue = '#09bd6c';


// Color Variables
const emptyColor = '#edeff5';

const setDoughnutChart = (chartData) => {
    // Destructure the chartData object to extract the necessary data
    const { chart, labels, label, data, backgroundColor = emptyColor } = chartData;

    // Check if all data values are zero
    const isEmptyData = data.every((value) => value === 0);

    // If data is empty, use a single color for the entire chart
    const chartLabels = isEmptyData ? ['No Data'] : labels;
    const chartDataValues = isEmptyData ? [1] : data;
    const chartBackgroundColors = isEmptyData ? [emptyColor] : backgroundColor || [chart_color_4, chart_color_2];

    // Get the chart context
    var ctx = document.getElementById(chart).getContext('2d');

    // Create the Doughnut Chart
    var myChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                label: label,
                data: chartDataValues,
                backgroundColor: chartBackgroundColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            cutout: '70%', // Doughnut cutout size for inner space
            plugins: {
                legend: {
                    display: false // Hide default legend
                },
                tooltip: {
                    enabled: true
                }
            }
        }
    });

    myChart.update();
};
const setLineChart = (chartData) => {
    // Destructure the chartData object to extract the necessary data
    const { chart, labels, label, data } = chartData;

    var ctx = document.getElementById(chart).getContext('2d');

    // Destroy the previous chart instance if it exists
    if (window.myCharts && window.myCharts[chart] instanceof Chart) {
        window.myCharts[chart].destroy();
    }

    var gradient = ctx.createLinearGradient(0, 0, 0, 400); // Vertical gradient
    gradient.addColorStop(0, 'rgba(54, 117, 235, 0.3)'); // Light translucent teal (base color with alpha)
    gradient.addColorStop(1, 'rgba(38, 67, 104, 0)');   // Fully transparent teal (base color with alpha)


    const dataMax = Math.max(...data);
    const buffer = dataMax * 0.1; // Add 10% buffer above max value
    const max = Math.ceil(dataMax + buffer);
    // Optionally round stepSize to make it nice

    const stepSize = Math.ceil(max / 5);

    // Line Chart (Responsive with new color settings)
    var lineChartConfig = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels, // X-axis labels
            datasets: [{
                label: label, // Label for the dataset
                data: data, // Y-axis data points
                backgroundColor: gradient, // Light green fill
                borderColor: chart_color_2, // Line border color
                fill: true, // Fill the area under the line
                tension: 0.4, // Smooth the curve
                pointBackgroundColor: chart_color_2, // Color for the points
                pointBorderColor: chart_color_1, // Border color for the points
                pointRadius: 5, // Size of the points
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false // Hide default legend
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                y: {
                    display: true,
                    beginAtZero: true, // Start the Y-axis at 0
                    max: max, // Set the Y-axis maximum value to 10000
                    ticks: {
                        stepSize: stepSize, // Controls the gap between tick marks
                        callback: function (value) {
                            return value; // Append '%' symbol to the tick values
                        }
                    },
                    grid: {
                        drawBorder: false,
                        color: 'rgba(204, 204, 204, 0.1)' // Custom grid color
                    }
                },
                x: {
                    display: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(204, 204, 204, 0.1)' // Custom grid color
                    }
                }
            }
        }
    });

    // Initialize myCharts object if it doesn't exist
    if (!window.myCharts) {
        window.myCharts = {};
    }

    // Store the chart instance using the chartId as the key
    window.myCharts[chart] = lineChartConfig;

    lineChartConfig.update();
}

const initializeStudentAssignments = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Submit', 'Not Submited'],
        label: 'Plagiarism Check',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: true,
        textLine1: "80%", // Custom text for the first line
        textLine2: "Complete" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['A Grade', 'B Grade', 'C Grade', 'D Grade'],
        label: 'Examination Grade',
        data: [20, 40, 40, 0], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentPaymentDetails = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Paid', 'Due'],
        label: 'Payments',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['Bank', 'Card'],
        label: 'Payments',
        data: [20, 80], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentExaminations = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Present', 'Absent'],
        label: 'Attend Examination',
        data: [80, 20], // Data for the chart
        backgroundColor: [chartRed, chartGreen], // Custom background colors
        text: true,
        textLine1: "80%", // Custom text for the first line
        textLine2: "Complete" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'gradeChart',
        labels: ['A Grade', 'B Grade', 'C Grade', 'D Grade'],
        label: 'Examination Grade',
        data: [20, 40, 40, 0], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });
}

const initializeStudentAttendance = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Absent', 'Present', 'Bank Payment'],
        label: 'Lecture Attendance',
        data: [80, 20], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });

    setLineChart({
        chart: 'lineChart',
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        label: 'Monthly Attendance',
        data: [10, 20, 30, 10, 20, 30, 10], // Data for the chart
    })
};

const initializeLectureAttendance = () => {
    setDoughnutChart({
        chart: 'myChart',
        labels: ['Absent', 'Present', 'Bank Payment'],
        label: 'Lecture Attendance',
        data: [80, 20], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors
        text: true,
        textLine1: "75%", // Custom text for the first line
        textLine2: "Performance" // Custom text for the second line
    });
    setLineChart({
        chart: 'lineChart',
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        label: 'Monthly Attendance',
        data: [10, 20, 30, 10, 20, 30, 10], // Data for the chart
    })
};

const initializeSelectedPaymentCategory = async (id) => {

    const paymentMethodChartCanvas = $("#paymentMethodChart");

    const bank = paymentMethodChartCanvas.data('bank') || 0;
    const card = paymentMethodChartCanvas.data('card') || 0;
    const cash = paymentMethodChartCanvas.data('cash') || 0;


    setDoughnutChart({
        chart: 'paymentMethodChart',
        labels: ['Bank', 'Card', 'Cash'],
        label: 'Payments',
        data: [bank, card, cash], // Data for the chart
        backgroundColor: [chartPink, chartOrange, chartPurple], // Custom background colors
        text: false,
        textLine1: "", // Custom text for the first line
        textLine2: "" // Custom text for the second line
    });

    await Promise.all([
        loadSelectedPaymentCategoryPaymentStatusCount(id)
    ]);

}

const initializeSelectedSubject = async (id) => {
    await Promise.all([
        loadSubjectAssignmentsPerformance(id),
        loadSubjectQuizExamPerformance(id)
    ]);

}

const initializeSelectedSubjectAssignment = (id) => {
    fetch(`api/getSelectedAssignmentChartData.php?assignment=${id}`)
        .then((response) => response.text())
        .then((data) => {
            //console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {

                setDoughnutChart({
                    chart: 'assignmentChart',
                    labels: response.labels,
                    label: 'Assignments',
                    data: response.data,
                    backgroundColor: [chart_color_1, chartGreen, chartYellow, chartRed], // Custom background colors

                });

                setDoughnutChart({
                    chart: 'assignmentMarkChart',
                    labels: response.labels2,
                    label: 'Assignments Marking Progress',
                    data: response.data2, // Data for the chart
                    backgroundColor: [chart_color_1, chartPurple, chartPink, chartGreen], // Custom background colors
                });


            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
        });
}

const initializeSelectedSubjectExamination = (id, type) => {
    //console.log(id);
    if (type == 2) {

        fetch('api/getOnlineExamChartData.php?id=' + id)
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {

                    setDoughnutChart({
                        chart: 'examinationChart',
                        labels: response.label,
                        label: 'Examination',
                        data: response.data, // Data for the chart
                        backgroundColor: [chart_color_1, chart_color_2], // Custom background colors

                    });
                    setDoughnutChart({
                        chart: 'examinationMarkChart',
                        labels: response.label2,
                        label: 'Examination Marking Progress',
                        data: response.data2, // Data for the chart
                        backgroundColor: [chart_color_1, chart_color_2, chart_color_3], // Custom background colors

                    });
                }

            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);

    }


}

const initializeSelectedSubjectQuiz = () => {
    setDoughnutChart({
        chart: 'quizChart',
        labels: ['Submit', 'Not-Submited'],
        label: 'Quiz',
        data: [40, 60], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });
    setDoughnutChart({
        chart: 'quizMarkChart',
        labels: ['0% - 25%', '25% - 50%', '50% - 75%', '75% - 100%'],
        label: 'Quiz Marking Progress',
        data: [50, 30, 20, 10], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializePlagiarismOverview = () => {
    setDoughnutChart({
        chart: 'plagiarismChart',
        labels: ['Physical Science', 'Computer Science', 'Food & Cookery'],
        label: 'Most Plagiarised Courses',
        data: [40, 50, 10], // Data for the chart
        backgroundColor: [chart_color_1, chart_color_2, chart_color_3], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializeSelectedPlagiarism = () => {
    setDoughnutChart({
        chart: 'plagiarismChart',
        labels: ['Uniq', 'Plagiarism'],
        label: 'Most Plagiarised Courses',
        data: [40, 60], // Data for the chart
        backgroundColor: [chartGreen, chartRed], // Custom background colors
        text: true,
        textLine1: "88%", // Custom text for the first line
        textLine2: "Total Performance" // Custom text for the second line
    });


}

const initializeDashboard = async () => {
    function generateMiniBars(values = []) {
        const max = Math.max(...values, 1);

        return values.map((value, index) => {
            const height = Math.max((value / max) * 36, 8);
            const activeClass = index === values.length - 1 ? "active" : "";

            return `<span class="stats-mini-bar ${activeClass}" style="height:${height}px;"></span>`;
        }).join("");
    }

    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
        try {
            const values = JSON.parse(chart.dataset.chart || "[]");
            chart.innerHTML = generateMiniBars(values);
        } catch (e) {
            chart.innerHTML = "";
        }
    });

    await Promise.all([
        loadStudentEngagemnet(),
        loadAssignmentsPerformance(),
        loadQuizExamPerformance(),
        loadIncomeData()
    ]);

    const chartCanvases = document.querySelectorAll('canvas[id^="paymentPieChart_"]');

    chartCanvases.forEach(canvas => {
        const awaiting = parseInt(canvas.getAttribute('data-awaiting')) || 0;
        const pending = parseInt(canvas.getAttribute('data-pending')) || 0;
        const approved = parseInt(canvas.getAttribute('data-approved')) || 0;
        const rejected = parseInt(canvas.getAttribute('data-rejected')) || 0;

        // Call the setDoughnutChart method
        setDoughnutChart({
            chart: canvas.id, // Use the canvas ID
            labels: ['Awaiting', 'Pending Approval', 'Approved', 'Rejected'],
            label: 'Payments',
            data: [awaiting, pending, approved, rejected], // Data for the chart
            backgroundColor: [chartYellow, chart_color_2, chartGreen, chartRed], // Custom background colors

        });
    });
}

const initializePayments = () => {
    const chartCanvases = document.querySelectorAll('canvas[id^="paymentPieChart_"]');

    // Loop through each canvas and initialize a pie chart
    chartCanvases.forEach(canvas => {
        const pending = parseInt(canvas.getAttribute('data-pending')) || 0;
        const approved = parseInt(canvas.getAttribute('data-approved')) || 0;
        const rejected = parseInt(canvas.getAttribute('data-rejected')) || 0;

        // Call the setDoughnutChart method
        setDoughnutChart({
            chart: canvas.id, // Use the canvas ID
            labels: ['Pending', 'Approved', 'Rejected'],
            label: 'Payments',
            data: [pending, approved, rejected], // Data for the chart
            backgroundColor: ['#0061D2', '#48CD50', '#D85E44'], // Custom background colors
            text: false, // Disable text in the center
            textLine1: "", // Custom text for the first line
            textLine2: "" // Custom text for the second line
        });
    });
};

const loadStudentEngagemnet = () => {
    const condition = $("#student-engagements-condition").val() || 1;

    return new Promise((resolve) => {
        fetch('api/getStudentsEngagementsData.php?condition=' + condition)
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    setLineChart({
                        chart: 'studentEngagementlineChart',
                        labels: response.labels,
                        label: 'Student Engagement',
                        data: response.data,
                    });
                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadAssignmentsPerformance = () => {
    return new Promise((resolve) => {
        fetch('api/getAssignmentsPerformance.php')
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0 && response.data[2] == 0 && response.data[3] == 0) {

                        setDoughnutChart({
                            chart: 'assignmentChart',
                            labels: [""],
                            label: 'Assignments',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    } else {

                        setDoughnutChart({
                            chart: 'assignmentChart',
                            labels: response.labels,
                            label: 'Assignments',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadQuizExamPerformance = () => {
    return new Promise((resolve) => {
        fetch('api/getQuizPerformance.php')
            .then((response) => response.text())
            .then((data) => {
                //console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0) {
                        setDoughnutChart({
                            chart: 'examChart',
                            labels: [""],
                            label: 'Quizes',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    } else {
                        setDoughnutChart({
                            chart: 'examChart',
                            labels: response.labels,
                            label: 'Quizes',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadIncomeData = () => {
    const condition = $("#income-condition").val() || 1;
    return new Promise((resolve) => {
        fetch('api/getIncomeData.php?condition=' + condition)
            .then((response) => response.text())
            .then((data) => {
                console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    setLineChart({
                        chart: "incomeLineChart",
                        labels: response.labels,
                        label: 'Income',
                        data: response.data,
                    });
                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });
}
const loadSubjectAssignmentsPerformance = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getSubjectAssignmentsPerformance.php?subject=${id}`)
            .then((response) => response.text())
            .then((data) => {

                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0 && response.data[2] == 0 && response.data[3] == 0) {

                        setDoughnutChart({
                            chart: 'subjectAssignmentChart',
                            labels: [""],
                            label: 'Assignments',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    } else {

                        setDoughnutChart({
                            chart: 'subjectAssignmentChart',
                            labels: response.labels,
                            label: 'Assignments',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors

                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadSubjectQuizExamPerformance = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getSubjectQuizPerformance.php?subject=${id}`)
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    if (response.data[0] == 0 && response.data[1] == 0) {
                        setDoughnutChart({
                            chart: 'subjectExamChart',
                            labels: [""],
                            label: 'Quizes',
                            data: [0, 0, 0, 0], // Data for the chart
                            backgroundColor: [emptyColor, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    } else {
                        setDoughnutChart({
                            chart: 'subjectExamChart',
                            labels: response.labels,
                            label: 'Quizes',
                            data: response.data, // Data for the chart
                            backgroundColor: [chart_color_1, chart_color_2, chart_color_3, chart_color_4], // Custom background colors
                        });
                    }

                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}
const loadSelectedPaymentCategoryPaymentStatusCount = (id) => {
    return new Promise((resolve) => {
        fetch(`api/getPaymentCategoryPaymentsStatusCount.php?category=${id}`)
            .then((response) => response.text())
            .then((data) => {

                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {

                    setDoughnutChart({
                        chart: 'myChart',
                        labels: response.labels,
                        label: 'Payments',
                        data: response.data,
                        backgroundColor: [chartYellow, chartOrange, chartGreen, chartRed, chartOrange, chartGreen, chartRed, chartGreen],
                    });


                }
            })
            .catch((error) => {
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const initializeLecturerDashboard = () => {
    fetch('api/getLecturerBusyTimelines')
        .then((response) => response.text())
        .then((data) => {
            //console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                const data = {
                    labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    datasets: [{
                        label: 'Busy Time',
                        data: response.data, // your PHP-generated data (with label field)
                        backgroundColor: 'rgba(54, 162, 235, 0.3)',
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                };

                const config = {
                    type: 'bar',
                    data: data,
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.raw.label || `${context.raw.x[0]} - ${context.raw.x[1]}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                min: 6,
                                max: 18,
                                ticks: {
                                    callback: function (value) {
                                        const hours = Math.floor(value);
                                        const minutes = Math.round((value - hours) * 60);
                                        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Time'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Day'
                                }
                            }
                        }
                    }
                };

                new Chart(document.getElementById('busyTimeline'), config);

            }
        })
        .catch((error) => {
            //console.log(error);
            setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
        });

}


const initializePerformance = (userId, target) => {

    function generateMiniBars(values = [], theme = "default") {

        const max = Math.max(...values, 1);
        const colors = getBarTheme(theme);

        return values.map((value, index) => {

            const height = Math.max((value / max) * 36, 8);
            const isActive = index === values.length - 1;

            return `
                <span 
                    class="stats-mini-bar ${isActive ? 'active' : ''}"
                    style="
                        height:${height}px;
                        background:${isActive ? colors.active : colors.normal};
                    ">
                </span>
            `;
        }).join("");
    }
    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
        try {
            const values = JSON.parse(chart.dataset.chart || "[]");
            const theme = chart.dataset.theme;
            chart.innerHTML = generateMiniBars(values, theme);
        } catch (e) {
            chart.innerHTML = "";
        }
    });

    loadTargetChart(userId, target);
    loadWorkedDayChart(userId);
}

let targetChart = null;
const loadTargetChart = (userId, target) => {
    const condition = $("#target-condition").val();

    fetch("api/getUserPerformance.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            id: userId,
            condition: condition,
        }),
    })
        .then((response) => response.json())
        .then((response) => {
            if (response.error) {
                setTimeout(() => {
                    showToast("error", response.error);
                }, 1000);
                return;
            }

            const hotLeads = response.hot || [];
            const warmLeads = response.warm || [];
            const busyLeads = response.busy || [];
            const sleepLeads = response.sleep || [];
            const enrolledLeads = response.enrolled || [];
            const futureLeads = response.future || [];
            const coldLeads = response.cold || [];
            const labels = response.labels || [];

            let topic = "Weekly Target Chart";
            let xTitle = "Days of the Week";

            if (condition == "2") {
                topic = "Monthly Target Chart";
                xTitle = "Days of the Month";
            } else if (condition == "3") {
                topic = "Yearly Target Chart";
                xTitle = "Months of the Year";
            }

            const monthlyTarget = parseInt(target) || 0;

            const currentDate = new Date();
            const selectedYear = parseInt(response.year) || currentDate.getFullYear();
            const selectedMonth = parseInt(response.month) || (currentDate.getMonth() + 1);

            const getWeeksInMonth = (year, month) => {
                const firstDate = new Date(year, month - 1, 1);
                const lastDate = new Date(year, month, 0);
                const totalDays = lastDate.getDate();
                const firstDay = firstDate.getDay();

                return Math.ceil((firstDay + totalDays) / 7);
            };

            let targetValue = monthlyTarget;

            if (condition == "1") {
                const weekCount = getWeeksInMonth(selectedYear, selectedMonth);
                targetValue = weekCount > 0 ? Math.round(monthlyTarget / weekCount) : 0;
            } else if (condition == "2") {
                targetValue = monthlyTarget;
            } else if (condition == "3") {
                targetValue = monthlyTarget;
            }

            console.log(target);
            console.log(targetValue);

            // very important: target data length must match labels length
            const targetLineData = labels.map(() => targetValue);

            const ctx = document.getElementById("targetChart").getContext("2d");

            if (targetChart) {
                targetChart.destroy();
            }

            targetChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: "Hot Leads",
                            data: hotLeads,
                            borderColor: "#FF0000",
                            backgroundColor: "rgba(255, 77, 79, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#FF0000"
                        },
                        {
                            label: "Warm Leads",
                            data: warmLeads,
                            borderColor: "#FFF200",
                            backgroundColor: "rgba(255, 242, 0, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#FFF200"
                        },
                        {
                            label: "Busy Leads",
                            data: enrolledLeads,
                            borderColor: "#2196F3",
                            backgroundColor: "rgba(33, 150, 243, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#2196F3"
                        },
                        {
                            label: "Sleep Leads",
                            data: enrolledLeads,
                            borderColor: "#7C3AED",
                            backgroundColor: "rgba(124, 58, 237, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#7C3AED"
                        },
                        {
                            label: "Enrolled Leads",
                            data: enrolledLeads,
                            borderColor: "#FF00FF",
                            backgroundColor: "rgba(255, 0, 255, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#FF00FF"
                        },
                        {
                            label: "Future Leads",
                            data: futureLeads,
                            borderColor: "#00FF00",
                            backgroundColor: "rgba(0, 255, 0, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#00FF00"
                        },
                        {
                            label: "Cold Leads",
                            data: coldLeads,
                            borderColor: "#6B7280",
                            backgroundColor: "rgba(107, 114, 128, 0.18)",
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: "#6B7280"
                        },

                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: "index",
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: "top",
                            labels: {
                                usePointStyle: true,
                                font: { weight: "bold" }
                            }
                        },
                        title: {
                            display: true,
                            text: topic,
                            font: { size: 18, weight: "bold" }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Number of Leads"
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: xTitle
                            }
                        }
                    }
                }
            });
        })
        .catch((error) => {
            console.log("Error:", error);
        });
};

const loadWorkedDayChart = (userId) => {
    const monthName = document.getElementById('monthName');
    const calendarDays = document.getElementById('calendarDays');
    let currentDate = new Date();

    let workingDays = [];

    fetch('api/getUserWorkingDays.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: userId
        })
    })
        .then(res => res.json())
        .then(data => {
            workingDays = data.workingDays;
            renderCalendar();
        });
    function renderCalendar() {
        calendarDays.innerHTML = '';

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        monthName.textContent = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });

        const firstDay = new Date(year, month, 1).getDay();
        const lastDate = new Date(year, month + 1, 0).getDate();

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            empty.classList.add('calendar-day', 'inactive');
            calendarDays.appendChild(empty);
        }

        for (let day = 1; day <= lastDate; day++) {
            // Create date in Asia/Colombo timezone
            const date = new Date(year, month, day);
            const formatted = date.toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' }); // YYYY-MM-DD

            const dayCell = document.createElement('div');
            dayCell.classList.add('calendar-day');
            dayCell.textContent = day;

            if (workingDays.includes(formatted)) {
                dayCell.classList.add('attended');
                dayCell.addEventListener('click', () => {
                    window.location.href = `user-day-performance.php?user-id=${userId}&date=${encodeURIComponent(formatted)}`;
                });
            }

            calendarDays.appendChild(dayCell);
        }
    }

    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });
}

let sourcePerformanceChart = null;

const initializeMarketingDashboard = () => {

    function generateMiniBars(values = [], theme = "default") {

        const max = Math.max(...values, 1);
        const colors = getBarTheme(theme);

        return values.map((value, index) => {

            const height = Math.max((value / max) * 36, 8);
            const isActive = index === values.length - 1;

            return `
                <span 
                    class="stats-mini-bar ${isActive ? 'active' : ''}"
                    style="
                        height:${height}px;
                        background:${isActive ? colors.active : colors.normal};
                    ">
                </span>
            `;
        }).join("");
    }
    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
        try {
            const values = JSON.parse(chart.dataset.chart || "[]");
            const theme = chart.dataset.theme;
            chart.innerHTML = generateMiniBars(values, theme);
        } catch (e) {
            chart.innerHTML = "";
        }
    });

    const leadTrendCtx = document.getElementById('leadTrendChart').getContext('2d');
    new Chart(leadTrendCtx, {
        type: 'line',
        data: {
            labels: leadTrendLabels,
            datasets: [{
                label: 'Warm Leads',
                data: warmTrendData,
                borderColor: '#FFF200',
                backgroundColor: 'rgba(255, 242, 0, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 4
            },
            {
                label: 'Enrolled Leads',
                data: completedTrendData,
                borderColor: '#FF00FF',
                backgroundColor: 'rgba(255, 0, 255, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 4
            }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            animation: {
                duration: 0
            }
        }
    });

    const statusBreakdownCtx = document.getElementById('statusBreakdownChart').getContext('2d');
    new Chart(statusBreakdownCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: [
                    '#FF0000',
                    '#FFF200',
                    '#FF9900',
                    '#00F0FF',
                    '#FF00FF',
                    '#00FF00',
                    '#BDBDBD'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
        }
    });

    const ctx = document.getElementById('sourcePerformanceChart').getContext('2d');

    sourcePerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sourceLabels,
            datasets: [
                {
                    label: 'Total Leads',
                    data: sourceLeadData,
                    backgroundColor: 'rgba(62,64,149,0.75)',
                    borderRadius: 6,
                    barThickness: 22
                },
                {
                    label: 'Warm Leads',
                    data: sourceWarmData,
                    backgroundColor: '#FFF200',
                    borderRadius: 6,
                    barThickness: 22
                },
                {
                    label: 'Enrolled Leads',
                    data: sourceEnrolledData,
                    backgroundColor: '#FF00FF',
                    borderRadius: 6,
                    barThickness: 22
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true
                }
            },
            animation: {
                duration: 0
            }
        }
    });
}

function getBarTheme(theme) {

    switch (theme) {

        case "hot":
            return {
                normal: "rgba(255, 0, 0, 0.18)",
                active: "#FF0000"
            };

        case "warm":
            return {
                normal: "rgba(255, 242, 0, 0.18)",
                active: "#FFF200"
            };

        case "busy":
            return {
                normal: "rgba(255, 153, 0, 0.18)",
                active: "#FF9900"
            };

        case "sleep":
            return {
                normal: "rgba(0, 240, 255, 0.18)",
                active: "#00F0FF"
            };

        case "enrolled":
            return {
                normal: "rgba(255, 0, 255, 0.18)",
                active: "#FF00FF"
            };

        case "future":
            return {
                normal: "rgba(0, 255, 0, 0.18)",
                active: "#00FF00"
            };

        case "cold":
            return {
                normal: "rgba(189, 189, 189, 0.18)",
                active: "#BDBDBD"
            };

        default:
            return {
                normal: "rgba(189, 189, 189, 0.18)",
                active: "#BDBDBD"
            };
    }
}

function loadSourcePerformanceChart(fromDate = '', toDate = '') {

    const data = {
        from_date: fromDate,
        to_date: toDate
    };

    console.log(data);

    fetch('api/sourcePerformanceChartData.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            recreateSourcePerformanceChart(data);
        })
        .catch(error => {
            console.error(error);
        });
}

function filterSourcePerformanceChart() {
    const fromDate = document.getElementById('sourceFromDate').value;
    const toDate = document.getElementById('sourceToDate').value;

    if (fromDate !== '' && toDate !== '' && fromDate > toDate) {
        showToast('warning', 'From date cannot be greater than To date.');
        return;
    }

    loadSourcePerformanceChart(fromDate, toDate);
}

function recreateSourcePerformanceChart(data) {
    console.log(data);

    const canvas = document.getElementById('sourcePerformanceChart');

    if (!canvas) {
        console.error("sourcePerformanceChart canvas not found");
        return;
    }

    const ctx = canvas.getContext('2d');

    if (sourcePerformanceChart && typeof sourcePerformanceChart.destroy === "function") {
        sourcePerformanceChart.destroy();
    }

    const labels = data.sourceLabels || [];
    const totalData = data.sourceLeadData || [];
    const warmData = data.sourceWarmData || [];
    const enrolledData = data.sourceEnrolledData || [];

    if (labels.length === 0) {
        console.warn("No chart data found");
    }

    sourcePerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Leads',
                    data: sourceLeadData,
                    backgroundColor: 'rgba(62,64,149,0.75)',
                    borderRadius: 6,
                    barThickness: 22
                },
                {
                    label: 'Warm Leads',
                    data: sourceWarmData,
                    backgroundColor: '#FFF200',
                    borderRadius: 6,
                    barThickness: 22
                },
                {
                    label: 'Enrolled Leads',
                    data: sourceEnrolledData,
                    backgroundColor: '#FF00FF',
                    borderRadius: 6,
                    barThickness: 22
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 200,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true
                }
            },
            animation: false
        }
    });

    sourcePerformanceChart.resize();
}
const initializeLeaveDashboard = () => {
    function generateMiniBars(values = []) {
        const max = Math.max(...values, 1);

        return values.map((value, index) => {
            const height = Math.max((value / max) * 36, 8);
            const activeClass = index === values.length - 1 ? "active" : "";

            return `<span class="stats-mini-bar ${activeClass}" style="height:${height}px;"></span>`;
        }).join("");
    }

    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
        try {
            const values = JSON.parse(chart.dataset.chart || "[]");
            chart.innerHTML = generateMiniBars(values);
        } catch (e) {
            chart.innerHTML = "";
        }
    });

    setTimeout(() => {
        document.getElementById("skeleton-list").classList.add("d-none");
        document.getElementById("real-list").classList.remove("d-none");
    }, 500);

    if (document.getElementById("leaveTrendChart")) {
        new Chart(document.getElementById("leaveTrendChart"), {
            type: "line",
            data: {
                labels: leaveTrendLabels,
                datasets: [{
                    label: "Requests",
                    data: leaveRequestTrendData,
                    tension: 0.4
                },
                {
                    label: "Approved",
                    data: leaveApprovedTrendData,
                    tension: 0.4
                }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    if (document.getElementById("leaveStatusChart")) {
        new Chart(document.getElementById("leaveStatusChart"), {
            type: "doughnut",
            data: {
                labels: leaveStatusLabels,
                datasets: [{
                    data: leaveStatusData
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    if (document.getElementById("leaveTypeChart")) {
        new Chart(document.getElementById("leaveTypeChart"), {
            type: "bar",
            data: {
                labels: leaveTypeLabels,
                datasets: [{
                    label: "Requests",
                    data: leaveTypeData
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}