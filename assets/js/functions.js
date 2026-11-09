const applyValidation = (response, validationMap, showMsg = true) => {
    if (showMsg) {
        showToast('error', 'Something went wrong.Check data fields and try again');
    }
    $('.contentBodyArea').animate({ scrollTop: 0 }, 'fast');
    Object.entries(validationMap).forEach(([key, selectors]) => {
        const [input, feedback] = selectors;

        if (response[key]) {
            $(input).addClass("is-invalid");
            $(feedback).text(response[key]);
        } else {
            $(input).removeClass("is-invalid");
            $(feedback).text("");
        }
    });
}

const signIn = (btn) => {
    toggleBtnLoader(btn, true);

    const email = $("#email").val().trim();
    const password = $("#password").val().trim();
    const rememberMe = $("#rememberMe").is(":checked");

    fetch("api/signIn.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            email: email,
            password: password,
            rememberMe: rememberMe
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                const validationMap = {
                    email: ["#email", "#email-feedback"],
                    password: ["#password", "#password-feedback"],
                };

                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const signOut = () => {
    fetch("api/signOut.php", {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            if (data.error) {
                localStorage.removeItem('theme');
                localStorage.removeItem("welcomeAlertShown");
                showToast("error", data.error);
            } else {
                window.location.reload();
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadDistrict = () => {
    const id = $("#province").val();
    fetch(`api/loadDistrict.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#district").html(data);
                $('#district').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadCities = () => {
    const id = $("#district").val();
    fetch(`api/loadCities.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#city").html(data);
                $('#city').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const addUser = (btn) => {

    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("vf", $("#vf-number").val());
    form.append("fen", $("#fingerprint_employee_no").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    const validationMap = {
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        title: ["#user-title", "#title-feedback"],
        email: ["#user-email", "#email-feedback"],
        contact: ["#user-contact-number", "#contact-feedback"],
        role: ["#user-role", "#role-feedback"],
        gender: ["#user-gender", "#gender-feedback"],
        image: ["#user-profile-image-container", "#image-feedback"],
        nic: ["#user-national-id", "#nic-feedback"],
        dob: ["#user-dob", "#dob-feedback"],
        address: ["#address-line-1", "#address-feedback"],
        address2: ["#address-line-2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#pcode-feedback"],
        cv: ["#file-info", "#cv-feedback"],
        vf_number: ["#vf-number", "#vf-feedback"],
        fingerprint_employee_no: ["#fingerprint_employee_no", "#fingerprint-employee-no-feedback"]
    };

    fetch("api/addUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {
            applyValidation(response, validationMap, false);
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location = './users', 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again.");
        });
};
const editUser = (btn, id) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("vf", $("#vf-number").val());
    form.append("fen", $("#fingerprint_employee_no").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    const validationMap = {
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        title: ["#user-title", "#title-feedback"],
        email: ["#user-email", "#email-feedback"],
        contact: ["#user-contact-number", "#contact-feedback"],
        role: ["#user-role", "#role-feedback"],
        gender: ["#user-gender", "#gender-feedback"],
        image: ["#user-profile-image-container", "#image-feedback"],
        nic: ["#user-national-id", "#nic-feedback"],
        dob: ["#user-dob", "#dob-feedback"],
        address: ["#address-line-1", "#address-feedback"],
        address2: ["#address-line-2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#pcode-feedback"],
        cv: ["#file-info", "#cv-feedback"],
        vf_number: ["#vf-number", "#vf-feedback"],
        fingerprint_employee_no: ["#fingerprint_employee_no", "#fingerprint-employee-no-feedback"]
    };

    fetch("api/editUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {

            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Network error. Please try again.");
        });
}
const filterUsers = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        role: $("#role-selector").val() || "",
        sort: $("#sort-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        update: $("#update-selector").val() || "",
        page: selectedPage || 1,
        selectUsers: selectedUsers || [],
        unSelectUsers: unSelectedUsers || [],
    };

    fetch('api/filterUsers.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const applyUsersUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedUsers: selectedUsers,
            uncheckedUsers: unSelectedUsers,
            currentPage: selectedPage,
            text: $("#user-search-text").val(),
            role: $("#role-selector").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.error) {
                    showToast("error", response.error);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    toggleBtnLoader(btn, "done");
                    showToast("success", response.done);
                    filterUsers();
                    setDefault();
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired user status change option.");
    }
}
const applySelectedUserUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedUsers: selectedUsers, uncheckedUsers: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterUsers();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                setTimeout(() => { showToast("error", "Something went wrong"); }, 1000);
            });
    } else {
        showToast("warning", "Please select the desired user status change option.");
    }
}

const addStudent = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("counselor", $("#user").val() || 0);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    appendStudentDocumentsToFormData(form);

    const physicalDocs = [];
    document.querySelectorAll(".physical-doc-checkbox-new:checked").forEach((cb) => physicalDocs.push(cb.value));
    form.append("physical_documents", JSON.stringify(physicalDocs));

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        counselor: ["#user", "#user-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
    };
    fetch("api/addStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("removed_documents", removedExistingDocuments);
    const physicalDocs = [];
    document.querySelectorAll(".physical-doc-checkbox-new:checked").forEach((cb) => physicalDocs.push(cb.value));
    form.append("physical_documents", JSON.stringify(physicalDocs));

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
    };

    fetch("api/editStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const uploadStudentBulk = async (btn) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            $("#csv-file-feedback").text('');
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                $("#csv-file-feedback").text('');
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.file);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                showToast("error", "An error occured.Please check error log and try again.");
                setTimeout(() => toggleBtnLoader(btn, false), 4000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 3000);
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 4000);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterStudents = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const applyStudentsUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    filterStudents();
                    setDefault();
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    showToast("error", response.error);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired student status change option.");
    }
}

const applySelectedStudentUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedStudents: selectedUsers, uncheckedStudents: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterStudents();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addFaculty = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("faculty_name", $("#faculty-name").val());
    form.append("faculty_cover", $("#cover-input").prop("files")[0]);

    fetch("api/addFaculty.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#addfaculty").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#faculty-name", "#af-name-feedback"],
                    cover: ["#faculty-cover", "#af-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editFaculty = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("faculty_id", id);
    form.append("faculty_name", $("#edit-faculty-name").val());
    form.append("faculty_cover", $("#edit-cover-input").prop("files")[0]);

    fetch("api/editFaculty.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editFaculty").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                const validationMap = {
                    name: ["#edit-faculty-name", "#ef-name-feedback"],
                    cover: ["#edit-faculty-cover", "#ef-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addProgramme = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("programme_name", $("#programme-name").val());
    form.append("programme_duration", $("#programme-duration").val());
    form.append("programme_cover", $("#cover-input").prop("files")[0]);
    form.append("new_programme_duration", $("#new-programme-duration").val());
    form.append("faculty_id", id);

    fetch("api/addPrograme.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#addProgramme").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#programme-name", "#ap-name-feedback"],
                    letter: ["#programme-letter", "#ap-letter-feedback"],
                    cover: ["#programme-cover", "#ap-cover-feedback"],
                    duration: ["#programme-duration", "#ap-duration-feedback"],
                    new_duration: ["#new-programme-duration", "#ap-new-duration-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editProgramme = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("programme_name", $("#edit-programme-name").val());
    form.append("programme_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("programme_duration", $("#edit-programme-duration").val());
    form.append("new_programme_duration", $("#new-edit-programme-duration").val());
    form.append("programme_id", id);

    fetch("api/editPrograme.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editProgramme").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-programme-name", "#ep-name-feedback"],
                    letter: ["#edit-programm-letter", "#ep-letter-feedback"],
                    cover: ["#edit-programm-cover", "#ep-cover-feedback"],
                    duration: ["#edit-programm-duration", "#ep-duration-feedback"],
                    new_duration: ["#new-edit-programme-duration", "#ep-new-duration-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const addIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_name", $("#intake-name").val());
    form.append("start", $("#start-date").val());
    form.append("end", $("#end-date").val());
    form.append("letter", $("#letter").val());
    form.append("sequence_number", $("#sequence-number").val());
    form.append("intake_cover", $("#cover-input").prop("files")[0]);
    form.append("programme_id", id);

    fetch("api/addIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                $("#addIntake").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#intake-name", "#ai-name-feedback"],
                    start: ["#start-date", "#start-feedback"],
                    end: ["#end-date", "#end-feedback"],
                    letter: ["#letter", "#letter-feedback"],
                    cover: ["#intake-cover", "#ai-cover-feedback"],
                    seq: ["#sequence-number", "#sequence-number-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_name", $("#edit-intake-name").val());
    form.append("start", $("#ei-start-date").val());
    form.append("end", $("#ei-end-date").val());
    form.append("letter", $("#ei-letter").val());
    form.append("sequence_number", $("#ei-sequence-number").val());
    form.append("intake_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("intake_id", id);

    fetch("api/editIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editIntake").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-intake-name", "#ei-name-feedback"],
                    start: ["#ei-start-date", "#ei-start-feedback"],
                    end: ["#ei-end-date", "#ei-end-feedback"],
                    letter: ["#ei-letter", "#ei-letter-feedback"],
                    cover: ["#edit-intake-cover", "#ei-cover-feedback"],
                    seq: ["#ei-sequence-number", "#ei-sequence-number-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addLevel = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("level_name", $("#level-name").val());
    form.append("level_cover", $("#cover-input").prop("files")[0]);
    form.append("intake_id", id);

    fetch("api/addLevel.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#addLevel").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#level-name", "#al-name-feedback"],
                    cover: ["#level-cover", "#al-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editLevel = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("level_name", $("#edit-level-name").val());
    form.append("level_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("level_id", id);

    fetch("api/editLevel.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editLevel").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-level-name", "#el-name-feedback"],
                    cover: ["#edit-level-cover", "#el-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_name", $("#subject-name").val());
    form.append("subject_hours", $("#subject-hours").val());
    form.append("subject_type", $("#subject-type").val());
    form.append("subject_group", $("#subject-group").val());
    form.append("new_subject_group", $("#new-subject-group").val());
    form.append("subject_cover", $("#cover-input").prop("files")[0]);
    form.append("level_id", id);

    fetch("api/addSubject.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                $("#addSubject").modal("hide");
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                const validationMap = {
                    name: ["#subject-name", "#as-name-feedback"],
                    hours: ["#subject-hours", "#as-hours-feedback"],
                    type: ["#subject-type", "#as-type-feedback"],
                    group: ["#subject-group", "#as-group-feedback"],
                    cover: ["#subject-cover", "#as-cover-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_name", $("#edit-subject-name").val());
    form.append("subject_hours", $("#edit-subject-hours").val());
    form.append("subject_type", $("#edit-subject-type").val());
    form.append("subject_group", $("#edit-subject-group").val());
    form.append("subject_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("subject_id", id);

    fetch("api/editSubject.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {

                const validationMap = {
                    name: ["#edit-subject-name", "#es-name-feedback"],
                    hours: ["#edit-subject-hours", "#es-hours-feedback"],
                    type: ["#edit-subject-type", "#es-type-feedback"],
                    group: ["#edit-subject-group", "#es-group-feedback"],
                    cover: ["#edit-subject-cover", "#es-cover-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadProgrammes = () => {
    const faculty = $("#faculty-selector").val();
    fetch(`api/loadProgrammes.php?id=${faculty}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#programme-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('faculty')) {
                    $("#programme-selector").attr("disabled", true);
                    $("#programme-selector").html("<option value='0'>- Select -</option>");
                    $("#faculty-selector").addClass("is-invalid");
                    $("#faculty-feedback").text(response.faculty);
                }
            } catch (error) {
                $("#faculty-selector").removeClass("is-invalid");
                $("#faculty-feedback").text("");
                $("#programme-selector").removeAttr("disabled");
                $("#programme-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadIntakes = () => {
    const programme = $("#programme-selector").val();
    fetch(`api/loadIntakes.php?id=${programme}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#intake-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('programme')) {
                    $("#intake-selector").attr("disabled", true);
                    $("#intake-selector").html("<option value='0'>- Select -</option>");
                    $("#programme-selector").addClass("is-invalid");
                    $("#programme-feedback").text(response.programme);
                }
            } catch (error) {
                $("#programme-selector").removeClass("is-invalid");
                $("#programme-feedback").text("");
                $("#intake-selector").removeAttr("disabled");
                $("#intake-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeDeletedFacultyProgrammes = (id) => {
    fetch(`api/removeDeletedFacultyProgrammes.php?id=${id}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                const multipleSelect = $("#programme-select2").get(0);
                for (let i = 0; i < multipleSelect.options.length; i++) {
                    ////console.log(multipleSelect.options[i].value);
                    response.programmes.forEach((value) => {
                        if (multipleSelect.options[i].value === value) {
                            removeItem(value, 'programme');
                        }
                    });
                }
                $("#programme-select1").attr("disabled", true);
                $("#programme-select1").html("<option value='0'>- Select -</option>");
                $("#intake-select1").attr("disabled", true);
                $("#intake-select1").html("<option value='0'>- Select -</option>");
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const removeDeletedProgrammeIntakes = (id) => {
    fetch(`api/removeDeletedProgrammeIntakes.php?id=${id}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                const multipleSelect = $("#intake-select2").get(0);
                for (let i = 0; i < multipleSelect.options.length; i++) {
                    ////console.log(multipleSelect.options[i].value);
                    response.intakes.forEach((value) => {
                        if (multipleSelect.options[i].value === value) {
                            removeItem(value, 'intake');
                        }
                    });
                }
                $("#intake-select1").attr("disabled", true);
                $("#intake-select1").html("<option value='0'>- Select Intake -</option>");
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterIntakeHasStudents = (id) => {
    const data = {
        id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        province: $("#province").val(),
        district: $("#district").val(),
        city: $("#city").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        sortById: sortCondition3,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    console.log(data);
    fetch('api/filterIntakeHasStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
                const count = document.getElementById('students-table-data').dataset.studentCount;
                $("#student-count").text(count + ' Students');
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const addAssignment = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#code").val());
    form.append("topic", $("#topic").val())
    form.append("start_date", $("#start-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_date", $("#end-date").val());
    form.append("end_time", $("#end-time").val());
    form.append("file", $("#file-selector-input").prop("files")[0]);
    form.append("plagiarism_report", $("#plagiarism-report").val());

    fetch('api/addAssignment.php', {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    code: ["#code", "#code-feedback"],
                    topic: ["#topic", "#topic-feedback"],
                    start_date: ["#start-date", "#start-date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_date: ["#end-date", "#end-date-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    file: ["#file-info", "#file-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        })
}

const editAssignment = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("assignment_id", id);
    form.append("code", $("#code").val());
    form.append("topic", $("#topic").val())
    form.append("start_date", $("#start-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_date", $("#end-date").val());
    form.append("end_time", $("#end-time").val());
    form.append("file", $("#file-selector-input").prop("files")[0]);
    form.append("plagiarism_report", $("#plagiarism-report").val());
    form.append("assignment_status", $("#assignment-status").val());

    if ($("#assignment-status").val() == 4 || $("#assignment-status").val() == 5) {
        form.append("late_submi_end_date", $("#late-submision-date").val());
        form.append("late_submi_end_time", $("#late-submision-time").val());
        form.append("recorection_fee", $("#recorrection-fee").val());
        form.append("recorrection_end_date", $("#recorrection-end-date").val());
        form.append("recorrection_end_time", $("#recorrection-end-time").val());
    }

    fetch('api/editAssignment.php', {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    code: ["#code", "#code-feedback"],
                    topic: ["#topic", "#topic-feedback"],
                    start_date: ["#start-date", "#start-date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_date: ["#end-date", "#end-date-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    file: ["#file-info", "#file-feedback"],
                    plagiarism_report: ["#plagiarism-report", "#plagiarism-report-feedback"],
                    assi_status: ["#assignment-status", "#assignment-status-feedback"],
                    late_submi_end_date: ["#late-submision-date", "#late-submision-date-feedback"],
                    recorection_fee: ["#recorrection-fee-date", "#recorrection-fee-date-feedback"],
                    recorrection_end_date: ["#recorrection-end-date", "#recorrection-end-date-feedback"],
                    recorrection_end_time: ["#recorrection-end-time", "#recorrection-end-time-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        })
}

const loadLevels = () => {
    const intake = $("#intake-selector").val();
    fetch(`api/loadLevels.php?id=${intake}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#level-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('intake')) {
                    $("#level-selector").attr("disabled", true);
                    $("#level-selector").html("<option value='0'>- Select -</option>");
                    $("#intake-selector").addClass("is-invalid");
                    $("#intake-feedback").text(response.intake);
                }
            } catch (error) {
                $("#intake-selector").removeClass("is-invalid");
                $("#intake-feedback").text("");
                $("#level-selector").removeAttr("disabled");
                $("#level-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadSubjects = () => {
    const level = $("#level-selector").val();
    fetch(`api/loadSubjects.php?id=${level}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#subject-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('level')) {
                    $("#subject-selector").attr("disabled", true);
                    $("#subject-selector").html("<option value='0'>- Select -</option>");
                    $("#level-selector").addClass("is-invalid");
                    $("#level-feedback").text(response.level);
                }
            } catch (error) {
                $("#level-selector").removeClass("is-invalid");
                $("#level-feedback").text("");
                $("#subject-selector").removeAttr("disabled");
                $("#subject-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadLecturers = () => {
    const subject = $("#subject-selector").val();
    fetch(`api/loadLecturers.php?id=${subject}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#lecturer-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('subject')) {
                    $("#lecturer-selector").attr("disabled", true);
                    $("#lecturer-selector").html("<option value='0'>- Select -</option>");
                    $("#subject-selector").addClass("is-invalid");
                    $("#subject-feedback").text(response.subject);
                }
            } catch (error) {
                $("#subject-selector").removeClass("is-invalid");
                $("#subject-feedback").text("");
                $("#lecturer-selector").removeAttr("disabled");
                $("#lecturer-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const scheduleLecture = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme_id", $("#programme-selector").val());
    form.append("intake_id", $("#intake-selector").val());
    form.append("level_id", $("#level-selector").val());
    form.append("subject_id", $("#subject-selector").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture-date", $("#lecture-date").val());

    form.append("title", $("#title").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("one_time", $("#oneTimeLecture").is(":checked") ? true : false);
    form.append("recurring", $("#recurringLecture").is(":checked") ? true : false);
    form.append("start_date", $("#start-date").val());
    form.append("end_date", $("#end-date").val());
    if ($("#recurringLecture").is(":checked")) {
        form.append("start_time", $("#r-start-time").val());
        form.append("end_time", $("#r-end-time").val());
    } else {
        form.append("start_time", $("#start-time").val());
        form.append("end_time", $("#end-time").val());
    }

    form.append("days", selectedDays);
    form.append("skip_poya_days", $("#skip-poya").is(":checked") ? true : false);
    form.append("skip_holidays", $("#skip-holidays").is(":checked") ? true : false);
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }


    const validationMap = {
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        level: ["#level-selector", "#level-feedback"],
        subject: ["#subject-selector", "#subject-feedback"],
        lecturer: ["#lecturer-selector", "#lecturer-feedback"],
        date: ["#lecture-date", "#date-feedback"],
        start_time: ["#start-time", "#start-time-feedback"],
        end_time: ["#end-time", "#end-time-feedback"],
        attachment_name: ["#attachment-name", "#attachment-name-feedback"],
        link: ["#link-input", "#link-feedback"],
        title: ["#title", "#title-feedback"],
        tutes: ["#file-selector-input", "#tutes-feedback"],
        start_date: ["#start-date", "#start-date-feedback"],
        end_date: ["#end-date", "#end-date-feedback"],
        days: ["#days", "#days-feedback"],
    };

    if ($("#recurringLecture").is(":checked")) {
        validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
        validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
    }

    fetch("api/scheduleLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "Something went wrong.Please try again later");
        });
}

const filterScheduledLectures = () => {
    const data = {
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        level: $("#level-selector").val(),
        subject: $("#subject-selector").val(),
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterScheduledLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadLecture = (subject) => {
    const data = {
        subject: subject,
        lecturer: $("#lecturer-selector").val(),
        date: $("#lecture-date").val(),
    }

    fetch(`api/loadLecture.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#lecture-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('subject')) {
                    $("#lecture-selector").attr("disabled", true);
                    $("#lecture-selector").html("<option value='0'>- Select lecturer -</option>");
                    $("#subject-selector").addClass("is-invalid");
                    $("#subject-feedback").text(response.subject);
                }
            } catch (error) {
                $("#subject-selector").removeClass("is-invalid");
                $("#subject-feedback").text("");
                $("#lecture-selector").removeAttr("disabled");
                $("#lecture-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const assignLecturerToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        hourlyRate: $("#lecturer-hourly-rate").val(),
        assignmentFee: $("#assignment-fee").val(),
        examinationFee: $("#examination-fee").val()
    }

    const validationMap = {
        lecturer: ["#lecturer-selector", "#lecturer-feedback"],
        hourly_rate: ["#lecturer-hourly-rate", "#hourly-rate-feedback"],
        assignment_fee: ["#assignmnet-fee", "#assignment-fee-feedback"],
        examination_fee: ["#examination-fee", "#examination-fee-feedback"],
    };

    fetch('api/assignLecturerToSubject.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editSubjectAssignLecturer = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        lecturerId: id,
        hourlyRate: $("#edit-lecturer-hourly-rate").val(),
        assignmentFee: $("#edit-assignment-fee").val(),
        examinationFee: $("#edit-examination-fee").val()
    }

    const validationMap = {
        hourly_rate: ["#edit-lecturer-hourly-rate", "#edit-hourly-rate-feedback"],
        assignment_fee: ["#edit-assignmnet-fee", "#edit-assignment-free-feedback"],
        examination_fee: ["#edit-examination-fee", "#edit-examination-fee-feedback"],
    };

    fetch('api/editSubjectAssignLecturer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const saveStudentPolicy = (btn) => {
    toggleBtnLoader(btn, true);
    fetch('api/saveStudentPolicy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ policy: studentPolicy.getHTMLCode() })
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, true);
            if (response.error) {
                showToast("error", response.error);
            } else {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addNews = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("content", newsContent.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        content: ["#div_editor1", "#content-feedback"],
        image: [null, "#image-feedback"]
    };

    fetch('api/addNews.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.replace("news"); }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editNews = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("content", newsContent.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        content: ["#div_editor1", "#content-feedback"],
        image: [null, "#image-feedback"]
    };

    fetch('api/editNews.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeNews = (id) => {
    toggleLoader(true);
    fetch('api/removeNews.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ news_id: id })
    })
        .then((res) => res.json())
        .then((data) => {
            toggleLoader(false);
            if (response.error) {
                showToast("error", response.error);
            } else {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadLectureRecording = (btn, lectureId) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", lectureId);
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("expire_date", $("#expire-date").val());
    form.append("recording_link", $("#recording-link").val());

    const validationMap = {
        cover: ["", "#cover-feedback"],
        expire_date: ["#expire-date", "#expire-date-feedback"],
        lecture: ["#lecture-selector", "#lecture-feedback"],
        recording_link: ["#recording-link", "#link-feedback"],
        image: ["", "#image-feedback"]
    };

    fetch('api/uploadLectureRecording.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectHasAssignments = (id) => {
    const data = {
        subject_id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByDate: sortCondition1,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectHasAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadAssignmentsMarksBulk = (btn, id) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("assignment_id", id);

    formData.append("file", $("#file-selector-input")[0].files[0]);

    console.log($("#file-selector-input")[0].files[0]);
    fetch("api/uploadAssignmentMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.file) {
                showToast("error", response.file);
                $('#csv-file-feedback').text(response.file);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        pdf.text(response.dataErrors[i][j], 8, y);
                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addAssignmentMark = (btn, id, intakeId) => {
    toggleBtnLoader(btn, true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                showToast("success", response.done);
                filterLPRecorrectionRequestStudentsAssignments(intakeId);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editAssignmentMark = (id, assignmentId) => {
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const addLecturerAssignmentMark = (id, intakeId) => {
    toggleLoader(true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);

            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPStudentsAssignments(intakeId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editLecturerAssignmentMark = (btn, id, intakeId) => {
    toggleBtnLoader(btn, true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editMark").modal('hide');
                showToast("success", response.done);
                filterLPStudentsAssignments(intakeId);
                toggleBtnLoader(btn, false);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    mark: ["#mark", "#mark-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const filterStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const addExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    fetch('api/addExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }

                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }

                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }

                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const scheduleExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    ////console.log($("#exam-type").val());
    const type = $("#exam-type").val();

    if (type == 1) {
        form.append("start_time", $("#physical-start-time").val());
        form.append("end_time", $("#physical-end-time").val());
        form.append("duration", $("#physical-duration").val());
        form.append("location", $("#physical-location").val());
    } else if (type == 2 || type == 3) {
        form.append("start_time", $("#online-start-time").val());
        form.append("end_time", $("#online-end-time").val());
        form.append("duration", $("#online-duration").val());
        form.append("file", $("#file-selector-input")[0].files[0]);

        if (type == 3) {
            form.append("quiz_type", $("#quiz-type").val());
        }
    }

    fetch('api/scheduleExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }
                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }
                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }
                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_start_time")) {
                    $("#physical-start-time").addClass("is-invalid");
                    $("#physical-start-time-feedback").text(response.physical_start_time);
                } else {
                    $("#physical-start-time").removeClass("is-invalid");
                    $("#physical-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("physical_end_time")) {
                    $("#physical-end-time").addClass("is-invalid");
                    $("#physical-end-time-feedback").text(response.physical_end_time);
                } else {
                    $("#physical-end-time").removeClass("is-invalid");
                    $("#physical-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_start_time")) {
                    $("#online-start-time").addClass("is-invalid");
                    $("#online-start-time-feedback").text(response.online_start_time);
                } else {
                    $("#online-start-time").removeClass("is-invalid");
                    $("#online-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_end_time")) {
                    $("#online-end-time").addClass("is-invalid");
                    $("#online-end-time-feedback").text(response.online_end_time);
                } else {
                    $("#online-end-time").removeClass("is-invalid");
                    $("#online-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("location")) {
                    $("#location").addClass("is-invalid");
                    $("#location-feedback").text(response.location);
                } else {
                    $("#location").removeClass("is-invalid");
                    $("#location-feedback").text("");
                }
                if (response.hasOwnProperty("file")) {
                    $("#file-selector-input").addClass("is-invalid");
                    $("#file-feedback").text(response.file);
                } else {
                    $("#file-selector-input").removeClass("is-invalid");
                    $("#file-feedback").text("");
                }
                if (response.hasOwnProperty("quiz_type")) {
                    $("#quiz-type").addClass("is-invalid");
                    $("#quiz-type-feedback").text(response.quiz_type);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $("#quiz-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_duration")) {
                    $("#physical-duration").addClass("is-invalid");
                    $("#physical-duration-feedback").text(response.physical_duration);
                } else {
                    $("#physical-duration").removeClass("is-invalid");
                    $("#physical-duration-feedback").text("");
                }
                if (response.hasOwnProperty("online_duration")) {
                    $("#online-duration").addClass("is-invalid");
                    $("#online-duration-feedback").text(response.online_duration);
                } else {
                    $("#online-duration").removeClass("is-invalid");
                    $("#online-duration-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editScheduledLecture = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("level_id", $("#level-selector").val());
    form.append("subject_id", $("#subject-selector").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture_date", $("#lecture-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    // Or for multiple files:
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editScheduledLecture.php", {
        method: "POST",
        body: form
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload() }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('date')) {
                    $("#lecture-date").addClass("is-invalid");
                    $("#date-feedback").text(response.date);
                } else {
                    $("#lecture-date").removeClass("is-invalid");
                    $("#date-feedback").text("");
                }
                if (response.hasOwnProperty('start_time')) {
                    $("#start-time").addClass("is-invalid");
                    $("#start-time-feedback").text(response.start_time);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $("#start-time-feedback").text("");
                }
                if (response.hasOwnProperty('end_time')) {
                    $("#end-time").addClass("is-invalid");
                    $("#end-time-feedback").text(response.end_time);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $("#end-time-feedback").text("");
                }
                if (response.hasOwnProperty('attachment_name')) {
                    $("#attachment-name").addClass("is-invalid");
                    $("#attachment-name-feedback").text(response.attachment_name);
                } else {
                    $("#attachment-name").removeClass("is-invalid");
                    $("#attachment-name-feedback").text("");
                }
                if (response.hasOwnProperty('link')) {
                    $("#link-input").addClass("is-invalid");
                    $("#link-feedback").text(response.link);
                } else {
                    $("#link-input").removeClass("is-invalid");
                    $("#link-feedback").text("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const assignLecturerToStudentsAssignments = (btn, id) => {
    const lecturer_id = $("#lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleBtnLoader(btn, true);
        const data = {
            assignment_id: id,
            search_text: $("#search-text").val(),
            sort: $("#sort-selector").val(),
            resultPerPage: $("#result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToStudentsAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.done) {
                    showToast("success", response.done);
                    filterStudentsAssignments(id);
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }
}

const filterSubjectHasExaminations = (id) => {
    ////console.log(id);
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        type: $("#type-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectHasExaminations.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#examinations-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const updateScheduledExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();

    form.append("exam_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    ////console.log($("#exam-type").val());
    const type = $("#exam-type").val();

    if (type == 1) {
        form.append("start_time", $("#physical-start-time").val());
        form.append("end_time", $("#physical-end-time").val());
        form.append("duration", $("#physical-duration").val());
        form.append("location", $("#physical-location").val());
    } else if (type == 2 || type == 3) {
        form.append("start_time", $("#online-start-time").val());
        form.append("end_time", $("#online-end-time").val());
        form.append("duration", $("#online-duration").val());
        form.append("file", $("#file-selector-input")[0].files[0]);

        if (type == 3) {
            form.append("quiz_type", $("#quiz-type").val());
        }
    }

    fetch('api/updateScheduleExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }
                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }
                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }
                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_start_time")) {
                    $("#physical-start-time").addClass("is-invalid");
                    $("#physical-start-time-feedback").text(response.physical_start_time);
                } else {
                    $("#physical-start-time").removeClass("is-invalid");
                    $("#physical-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("physical_end_time")) {
                    $("#physical-end-time").addClass("is-invalid");
                    $("#physical-end-time-feedback").text(response.physical_end_time);
                } else {
                    $("#physical-end-time").removeClass("is-invalid");
                    $("#physical-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_start_time")) {
                    $("#online-start-time").addClass("is-invalid");
                    $("#online-start-time-feedback").text(response.online_start_time);
                } else {
                    $("#online-start-time").removeClass("is-invalid");
                    $("#online-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_end_time")) {
                    $("#online-end-time").addClass("is-invalid");
                    $("#online-end-time-feedback").text(response.online_end_time);
                } else {
                    $("#online-end-time").removeClass("is-invalid");
                    $("#online-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("location")) {
                    $("#location").addClass("is-invalid");
                    $("#location-feedback").text(response.location);
                } else {
                    $("#location").removeClass("is-invalid");
                    $("#location-feedback").text("");
                }
                if (response.hasOwnProperty("file")) {
                    $("#file-selector-input").addClass("is-invalid");
                    $("#file-feedback").text(response.file);
                } else {
                    $("#file-selector-input").removeClass("is-invalid");
                    $("#file-feedback").text("");
                }
                if (response.hasOwnProperty("quiz_type")) {
                    $("#quiz-type").addClass("is-invalid");
                    $("#quiz-type-feedback").text(response.quiz_type);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $("#quiz-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_duration")) {
                    $("#physical-duration").addClass("is-invalid");
                    $("#physical-duration-feedback").text(response.physical_duration);
                } else {
                    $("#physical-duration").removeClass("is-invalid");
                    $("#physical-duration-feedback").text("");
                }
                if (response.hasOwnProperty("online_duration")) {
                    $("#online-duration").addClass("is-invalid");
                    $("#online-duration-feedback").text(response.online_duration);
                } else {
                    $("#online-duration").removeClass("is-invalid");
                    $("#online-duration-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
var addNewPermissionsUserCount = 0;
var newPermissionUsers = [];
const loadAvailableUserFacultyPermission = (btn, facultyId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserFacultyPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        toggleBtnLoader(btn, false);
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, true);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const facultyPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/facultyPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);
                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addFacultyUserPermissions = (btn, facultyId, status) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            usersPermissions: userPermissions,
        };
        fetch('api/addFacultyUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterFacultyPermissions = (facultyId) => {
    const data = {
        facultyId: facultyId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterFacultyPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateFacultyUserPermission = (btn, facultyId, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateFacultyUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserProgrammePermission = (btn, programmeId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            programmeId: programmeId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserProgrammePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const programmePermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/programmePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addProgrammeUserPermissions = (btn, programmeId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            programmeId: programmeId,
            usersPermissions: userPermissions,
        };
        fetch('api/addProgrammeUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterProgrammePermissions = (programmeId) => {
    const data = {
        programmeId: programmeId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterProgrammePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateProgrammeUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateProgrammeUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserIntakePermission = (btn, intakeId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            intakeId: intakeId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserIntakePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Failed to add user. Please try again.");
            });
    }
};

const intakePermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/intakePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntakeUserPermissions = (btn, intakeId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            intakeId: intakeId,
            usersPermissions: userPermissions,
        };
        fetch('api/addIntakeUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterIntakePermissions = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterIntakePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "Failed to filter permissions. Please try again.");
        });
}

const updateIntakeUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateIntakeUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserLevelPermission = (btn, levelId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId: levelId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLevelPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const levelPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/levelPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addLevelUserPermissions = (btn, levelId) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId: levelId,
            usersPermissions: userPermissions,
        };
        fetch('api/addLevelUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                } else if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterLevelPermissions = (levelId) => {
    const data = {
        levelId: levelId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLevelPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLevelUserPermission = (btn, levelId, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else if (selectedPermissions.length == 0) {
        showToast("warning", "This user already has all permissions in this status. Please select another status to continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId, levelId,
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateLevelUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserSubjectPermission = (btn, subjectId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            subjectId: subjectId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserSubjectPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                        toggleBtnLoader(btn, false);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};
const subjectPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/subjectPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addSubjectUserPermissions = (btn, subjectId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            subjectId: subjectId,
            usersPermissions: userPermissions,
        };
        fetch('api/addSubjectUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterSubjectPermissions = (subjectId) => {
    const data = {
        subjectId: subjectId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateSubjectUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateSubjectUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const addQuiz = (subjectId) => {
    toggleLoader(true);
    const quizTitle = $("#quiz-title").val();
    const examDate = $("#exam-date").val();
    const startTime = $("#start-time").val();
    const endDate = $("#end-date").val();
    const endTime = $("#end-time").val();
    const duration = $("#duration").val();
    const quizType = $("#quiz-type").val();
    const quizOrder = $("#quiz-order").val();

    const data = {
        subjectId: subjectId,
        quizTitle: quizTitle,
        examDate: examDate,
        startTime: startTime,
        endDate: endDate,
        endTime: endTime,
        duration: duration,
        quizType: quizType,
        quizOrder: quizOrder
    }

    fetch('api/addQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.href = "add-quiz-questions?quiz-id=" + response.quizId; }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('title')) {
                    $("#quiz-title").addClass("is-invalid");
                    $('#quiz-title-feedback').html(response.title);
                } else {
                    $("#quiz-title").removeClass("is-invalid");
                    $('#quiz-title-feedback').html("");
                }
                if (response.hasOwnProperty('startDate')) {
                    $("#exam-date").addClass("is-invalid");
                    $('#exam-date-feedback').html(response.startDate);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $('#exam-date-feedback').html("");
                }

                if (response.hasOwnProperty('startTime')) {
                    $("#start-time").addClass("is-invalid");
                    $('#start-time-feedback').html(response.startTime);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $('#start-time-feedback').html("");
                }

                if (response.hasOwnProperty('endDate')) {
                    $("#end-date").addClass("is-invalid");
                    $('#end-date-feedback').html(response.endDate);
                } else {
                    $("#end-date").removeClass("is-invalid");
                    $('#end-date-feedback').html("");
                }

                if (response.hasOwnProperty('endTime')) {
                    $("#end-time").addClass("is-invalid");
                    $('#end-time-feedback').html(response.endTime);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $('#end-time-feedback').html("");
                }

                if (response.hasOwnProperty('duration')) {
                    $("#duration").addClass("is-invalid");
                    $('#duration-feedback').html(response.duration);
                } else {
                    $("#duration").removeClass("is-invalid");
                    $('#duration-feedback').html("");
                }

                if (response.hasOwnProperty('quizType')) {
                    $("#quiz-type").addClass("is-invalid");
                    $('#quiz-type-feedback').html(response.quizType);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $('#quiz-type-feedback').html("");
                }
                if (response.hasOwnProperty('quizOrder')) {
                    $("#quiz-order").addClass("is-invalid");
                    $('#quiz-order-feedback').html(response.quizOrder);
                } else {
                    $("#quiz-order").removeClass("is-invalid");
                    $('#quiz-order-feedback').html("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const saveQuizQuestions = (quizId, subjectId) => {
    const quizzes = [];
    $('.quiz-item').each(function () {
        const quizId = $(this).data('quiz-id');
        const question = $(this).find('.question-input').val();
        let correctAnswer = null;
        const answers = [];
        $(this).find('.answer-input').each(function (index) {
            const isCorrect = $(this).siblings('.answer-checkbox').is(':checked');
            answers.push({
                option: $(this).val(),
                isCorrect: isCorrect
            });

            if (isCorrect) {
                correctAnswer = index + 1;
            }
        });
        quizzes.push({
            quizId,
            question,
            answers,
            correctAnswer
        });
    });

    const data = {
        quizzes: quizzes,
        quizId: quizId,
    }

    ////console.log(quizzes);
    toggleLoader(true);
    fetch('api/addQuizQuestions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location = `subject-quizes?subject_id=${subjectId}` }, 4000);
            } else if (response.hasOwnProperty('errors')) {
                setTimeout(() => {
                    showToast("error", "An error occurred. Please check the error log and try again.");
                }, 1000);

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();
                let y = 10;
                pdf.setFontSize(10);

                for (let i = 0; i < response.errors.length; i++) {
                    pdf.text(response.errors[i], 8, y);
                    y += 10;
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            } else {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterQuizes = (subjectId) => {
    const data = {
        subjectId: subjectId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,

    }

    fetch('api/filterQuizes.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#quizes-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const updateQuiz = (quizId) => {
    toggleLoader(true);
    const quizTitle = $("#quiz-title").val();
    const examDate = $("#exam-date").val();
    const startTime = $("#start-time").val();
    const endDate = $("#end-date").val();
    const endTime = $("#end-time").val();
    const duration = $("#duration").val();
    const quizType = $("#quiz-type").val();
    const quizOrder = $("#quiz-order").val();

    const data = {
        quizId: quizId,
        quizTitle: quizTitle,
        examDate: examDate,
        startTime: startTime,
        endDate: endDate,
        endTime: endTime,
        duration: duration,
        quizType: quizType,
        quizOrder: quizOrder
    }

    fetch('api/updateQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('title')) {
                    $("#quiz-title").addClass("is-invalid");
                    $('#quiz-title-feedback').html(response.title);
                } else {
                    $("#quiz-title").removeClass("is-invalid");
                    $('#quiz-title-feedback').html("");
                }

                if (response.hasOwnProperty('date')) {
                    $("#exam-date").addClass("is-invalid");
                    $('#exam-date-feedback').html(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $('#exam-date-feedback').html("");
                }

                if (response.hasOwnProperty('startTime')) {
                    $("#start-time").addClass("is-invalid");
                    $('#start-time-feedback').html(response.startTime);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $('#start-time-feedback').html("");
                }

                if (response.hasOwnProperty('endTime')) {
                    $("#end-time").addClass("is-invalid");
                    $('#end-time-feedback').html(response.endTime);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $('#end-time-feedback').html("");
                }

                if (response.hasOwnProperty('quizType')) {
                    $("#quiz-type").addClass("is-invalid");
                    $('#quiz-type-feedback').html(response.quizType);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $('#quiz-type-feedback').html("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const updateQuizQuestions = (quizId) => {
    const quizzes = [];
    $('.quiz-item').each(function () {
        const quizId = $(this).data('quiz-id');
        const quizStatus = $(this).data('quiz-status');
        const question = $(this).find('.question-input').val();
        let correctAnswer = null;
        const answers = [];
        $(this).find('.answer-input').each(function (index) {
            const isCorrect = $(this).siblings('.answer-checkbox').is(':checked');
            answers.push({
                option: $(this).val(),
                isCorrect: isCorrect,
                answerId: $(this).data('answer-id')
            });

            if (isCorrect) {
                correctAnswer = index + 1;
            }
        });
        quizzes.push({
            quizId,
            quizStatus,
            question,
            answers,
            correctAnswer
        });
    });

    const data = {
        quizzes: quizzes,
        removeQuizes: removeQuizes,
        quizId: quizId,
    }

    ////console.log(quizzes);
    toggleLoader(true);
    fetch('api/updateQuizQuestions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('errors')) {
                setTimeout(() => {
                    showToast("error", "An error occurred. Please check the error log and try again.");
                }, 1000);

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();
                let y = 10;
                pdf.setFontSize(10);

                for (let i = 0; i < response.errors.length; i++) {
                    pdf.text(response.errors[i], 8, y);
                    y += 10;
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            } else {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeQuizPermanantly = () => {
    $("#removeConfirmModal").modal("hide");
    toggleLoader(true);
    const quizId = $('#removeConfirmModal').data('quiz-id');

    fetch('api/removeQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ quizId: quizId }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentToIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("counselor", $("#user").val() || 0);
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    appendStudentDocumentsToFormData(form);

    const physicalDocs = [];
    document.querySelectorAll(".physical-doc-checkbox-new:checked").forEach((cb) => physicalDocs.push(cb.value));
    form.append("physical_documents", JSON.stringify(physicalDocs));

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        counselor: ["#user", "#user-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
    };

    fetch("api/addStudentToIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.history.back(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentToPaymentPlan = (btn, studentHasIntakeId, status) => {
    toggleBtnLoader(btn, true);
    const data = {
        studentHasIntakeId: studentHasIntakeId,
        paymentCategoryId: $("#payment-category").val(),
        paymentPlanId: $("#payment-plan").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        paymentPeriod: $("#period").val(),
        discountNote: $("#discount-note").val(),
    }

    const validationMap = {
        paymentCategory: ["#payment-category", "#payment-category-feedback"],
        paymentPlan: ["#payment-plan", "#payment-plan-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        discountNote: ["#discount-note", "#discount-note-feedback"],
        paymentPeriod: ["#period", "#period-feedback"]
    };

    applyValidation('', validationMap, false);

    fetch('api/addStudentToPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                if (status == 2) {
                    setTimeout(() => { window.location = "./lead-registered-student-payments?student-id=" + studentHasIntakeId; }, 3000);
                } else {
                    setTimeout(() => { window.location = "./intake-student-payments?student-id=" + studentHasIntakeId; }, 3000);
                }

            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
}

const updatePaymentStatus = (btn, paymentId) => {
    toggleBtnLoader(btn, true);

    // Capture whether the super-admin checked the override box
    const forceUpdate = $("#force-update-status").is(":checked") ? 1 : 0;

    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        amount: $("#paid-amount").val(),
        method: $("#payment-method").val(),
        date: $("#paid-date").val(),
        note: $("#note").val(),
        forceUpdate: forceUpdate // Sent over JSON payload
    }

    console.log(data);

    fetch('api/updatePaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong. Check data fields and try again"); }, 1000);

                // Validation highlighting for the amount field if triggered by backend logic
                if (response.hasOwnProperty('amount')) {
                    $("#paid-amount").addClass("is-invalid");
                    $("#paid-amount-feedback").text(response.amount);
                } else {
                    $("#paid-amount").removeClass("is-invalid");
                    $("#paid-amount-feedback").text("");
                }

                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }
            }
        })
}
const filterPayments = (categoryId) => {
    const data = {
        categoryId: categoryId,
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        sort: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaymentCategory = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        name: $("#new-category-name").val(),
        currency: $("#currency").val()
    }

    const validationMap = {
        name: ["#new-category-name", "#new-category-name-feedback"],
        currency: ["#currency", "#currency-feedback"]
    };


    fetch('api/addPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaymentCategory = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        categoryId: id,
        name: $("#edit-category-name").val(),
        currency: $("#edit-category-currency").val()
    }

    const validationMap = {
        name: ["#edit-category-name", "#edit-category-name-feedback"],
        currency: ["#edit-category-currency", "#edit-category-currency-feedback"]
    };

    fetch('api/editPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterIntakePayments = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#payment-search-text").val(),
        category: $("#payment-category-selector").val(),
        plan: $("#payment-plan-selector").val(),
        type: $("#payment-type-selector").val(),
        status: $("#payment-status-selector").val(),
        sort: $('#payment-sort-selector').val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition3,
        sortById: sortCondition4,
        resultPerPage: $("#payment-show-result-selector").val(),
        page: selectedPage2,
    }

    console.log(data);

    fetch('api/filterIntakePayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentsToSubject = (id) => {
    toggleLoader(true);
    const data = {
        subjectId: id,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    fetch('api/addStudentsToSubject.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false)
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectAddStudents = (id) => {
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    ////console.log(data);

    fetch('api/filterSubjectAddStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterSubjectStudents = (id) => {
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    ////console.log(data);

    fetch('api/filterSubjectStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const applySubjectStudentsUpdate = (id) => {
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            subjectId: id,
            update: update,
            selectStudents: selectedUsers,
            unSelectStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        ////console.log(data);
        fetch('api/updateSubjectStudents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                toggleLoader(false);
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectStudents(id);
                    setDefault();
                } else if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    setTimeout(() => { showToast("warning", response.warning); }, 1000);
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}


const addStudentsToPaymentPlan = (btn, intakeId) => {
    toggleBtnLoader(btn, true);
    const data = {
        intakeId: intakeId,
        paymentPlanId: $("#payment-plan").val(),
        checkedStudents: selectedUsers,
        uncheckedStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    const validationMap = {
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        paymentCategory: ["#payment-category", "#payment-category-feedback"],
        paymentPlan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addStudentsToPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const addStudentsToPayment = (intakeId, categoryId, planId) => {
    const data = {
        intakeId: intakeId,
        paymentCategoryId: categoryId,
        paymentPlanId: planId,
        paymentType: $("#payment-type").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
        checkedStudents: selectedUsers,
        uncheckedStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    fetch('api/addStudentsToPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('paymentType')) {
                    $("#payment-type").addClass("is-invalid");
                    $("#payment-type-feedback").text(response.paymentType);
                } else {
                    $("#payment-type").removeClass("is-invalid");
                    $("#payment-type-feedback").text("");
                }
                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }
                if (response.hasOwnProperty('discountType')) {
                    $("#discount-type").addClass("is-invalid");
                    $("#discount-type-feedback").text(response.discountType);
                } else {
                    $("#discount-type").removeClass("is-invalid");
                    $("#discount-type-feedback").text("");
                }
                if (response.hasOwnProperty('discountAmount')) {
                    $("#discount").addClass("is-invalid");
                    $("#discount-feedback").text(response.discountAmount);
                } else {
                    $("#discount").removeClass("is-invalid");
                    $("#discount-feedback").text("");
                }
                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
        });
}

const addIntakeStudentPayment = (btn, paymentPlanId, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        paymentPlanId: paymentPlanId,
        data: collectPaymentData()
    }

    const validationMap = {
        paymentType: ['#payment-type', '#payment-type-feedback'],
        amount: ['#payment-amount', '#payment-amount-feedback'],
        discount: ['#discount', '#discount-feedback'],
        dueDate: ['#due-date', '#due-date-feedback'],
        penalty: ['#panelty-option', '#panelty-option-feedback'],
    };

    fetch('api/addIntakeStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);

            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);

                if (response.penalty_data) {
                    generatePenaltyPaymentErrorPDF(response);
                }
            }

        }).catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const scheduleLectureToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture-date", $("#lecture-date").val());

    form.append("title", $("#title").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("one_time", $("#oneTimeLecture").is(":checked") ? true : false);
    form.append("recurring", $("#recurringLecture").is(":checked") ? true : false);
    form.append("start_date", $("#start-date").val());
    form.append("end_date", $("#end-date").val());
    if ($("#recurringLecture").is(":checked")) {
        form.append("start_time", $("#r-start-time").val());
        form.append("end_time", $("#r-end-time").val());
    } else {
        form.append("start_time", $("#start-time").val());
        form.append("end_time", $("#end-time").val());
    }

    form.append("days", selectedDays);
    form.append("skip_poya_days", $("#skip-poya").is(":checked") ? true : false);
    form.append("skip_holidays", $("#skip-holidays").is(":checked") ? true : false);
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }
    const validationMap = {
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        level: ["#level-selector", "#level-feedback"],
        subject: ["#subject-selector", "#subject-feedback"],
        lecturer: ["#lecturer-selector", "#lecturer-feedback"],
        date: ["#lecture-date", "#date-feedback"],
        start_time: ["#start-time", "#start-time-feedback"],
        end_time: ["#end-time", "#end-time-feedback"],
        attachment_name: ["#attachment-name", "#attachment-name-feedback"],
        link: ["#link-input", "#link-feedback"],
        title: ["#title", "#title-feedback"],
        tutes: ["#file-selector-input", "#tutes-feedback"],
        start_date: ["#start-date", "#start-date-feedback"],
        end_date: ["#end-date", "#end-date-feedback"],
        days: ["#days", "#days-feedback"],
    };

    if ($("#recurringLecture").is(":checked")) {
        validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
        validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
    }

    fetch("api/scheduleLectureToSubject.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "Something went wrong.Please try again later");
        });
}

const filterSubjectScheduledLectures = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }



    fetch('api/filterSubjectScheduledLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLecture = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("title", $("#title").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture_date", $("#lecture-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append("status", $("#status-selector").val());
    form.append("physical", $("#physical-checkbox").is(":checked"));
    form.append("online", $("#online-checkbox").is(":checked"));
    form.append("recording", $("#recording-checkbox").length ? $("#recording-checkbox").is(":checked") : false);
    form.append("link", $("#link-input").val());

    // Cancellation reason (only meaningful when status = Cancelled)
    form.append("reason", $("#cancel-reason").val());

    // Postponed date/time + the linked occurrence id, if this lecture was
    // already postponed before (empty string means "no linked record yet")
    form.append("postponed_date", $("#postpond-date").val());
    form.append("postponed_start_time", $("#postpond-start-time").val());
    form.append("postponed_end_time", $("#postpond-end-time").val());
    form.append("linked_time_table_id", $("#linked-time-table-id").val());

    const files = $("#file-selector-input")[0].files;
    for (let i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    date: ["#lecture-date", "#date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                    lecturer: ["#lecturer-input", "#lecturer-feedback"],
                    title: ["#title", "#title-feedback"],
                    reason: ["#cancel-reason", "#cancel-reason-feedback"],
                    postponed_date: ["#postpond-date", "#postpond-date-feedback"],
                    postponed_start_time: ["#postpond-start-time", "#postpond-start-time-feedback"],
                    postponed_end_time: ["#postpond-end-time", "#postpond-end-time-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }

        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLectureRecording = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("recording", id);
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("expire_date", $("#expire-date").val());
    form.append("recording_link", $("#recording-link").val());

    fetch('api/editLectureRecording.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    image: [null, "#cover-feedback"],
                    expire_date: ["#expire-date", "#expire-date-feedback"],
                    lecture: ["#lecture-selector", "#lecture-feedback"],
                    recording_link: ["#recording-link", "#link-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPreRecordedLectureToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("duration", $("#duration").val());
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/addPreRecordedLectureToSubject.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    lecturer: ["#lecturer-selector", "#lecturer-feedback"],
                    duration: ["#duration", "#duration-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }

        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editSubjectPreRecordedLecture = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("duration", $("#duration").val());
    form.append("status", $("#status-selector").val());
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editSubjectPreRecordedLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const saveBank = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("bank", $("#bank-name").val());
    form.append("acc_no", $("#acc-number").val());
    form.append("acc_name", $("#acc-name").val());
    form.append("branch", $("#branch").val());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        bank: ["#bank-name", "#bank-name-feedback"],
        acc_no: ["#acc-number", "#acc-number-feedback"],
        acc_name: ["#acc-name", "#acc-name-feedback"],
        branch: ["#branch", "#branch-feedback"],
        cover: ["", "#cover-feedback"]
    }

    fetch('api/addBank.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);

            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserUsersPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const UsersPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/usersPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserStudentsPermission = (btn) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserStudentPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}
const studentsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/studentPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addUsersOtherPermissions = (btn, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            usersPermissions: userPermissions,
        };
        fetch('api/addUsersOtherPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json()) // Get the response as text
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload() }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterUsersPermissions = () => {
    console.log(selectedPermissions);
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterUsersPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateUsersOtherPermission = (btn, userId, type) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            type: type,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateUsersOtherPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, 'done');
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, true);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const displayUserPermissions = (data) => {
    newPermissionUsers = newPermissionUsers.concat(selectedUsers);
    $('#search-text').val('');
    $('#search-text').removeClass('is-invalid');
    $('#search-text-feedback').text('');
    $('#users-table-container').html('');
    $('#addNewUserModal').modal('hide');
    addNewPermissionsUserCount += selectedUsers.length;
    selectedUsers = [];
    $('#empty-users-display-area').addClass('d-none');
    $('#user-detailss-display-area').append(data);
    $('#save-permission-btn-area').removeClass('d-none');

}

const downloadStudentsCSV = () => {
    const modal = new bootstrap.Modal($('#csvDownload')[0]);
    modal.show();

    const waitingMessage = $('#waitingMessage');
    const successMessage = $('#successMessage');

    waitingMessage.show();
    successMessage.hide();

    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
    };

    fetch('api/downloadStudentsCSV.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => {
            console.log(response);
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("text/csv")) {
                ////console.log("text/csv");
                return response.blob();

            } else {
                ////console.log("erro/csv");
                return response.json();
            }
        })
        .then((data) => {
            ////console.log(data);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'students.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                waitingMessage.hide();
                successMessage.show();
                ////console.log("223223")
            } else {
                ////console.log("2233")
                // Handle JSON error response
                if (data.error) {
                    showToast("error", data.error);
                } else {
                    showToast("error", "An unknown error occurred.");
                }
                modal.hide(); // Hide modal on error
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again later.");
            modal.hide(); // Hide modal on error
        });
};

const applySubjectHasLecturersUpdate = (id) => {
    const update = $("#lecturers-update-selector").val();
    if (update != 0) {
        const data = {
            subjectId: id,
            update: update,
            checkedLecturers: selectedLecturers,
            uncheckedLecturers: unSelectedLecturers
        }
        fetch('api/applySubjectHasLecturersUpdate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectHasLecturers(id);
                    setDefault();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}
const filterSubjectHasLecturers = (id) => {
    console.log(sortCondition1);
    const data = {
        id: id,
        text: $("#lecturers-search-text").val(),
        sort: $("#lecturers-sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#lecturers-update-selector").val(),
        resultPerPage: $("#lecturers-show-result-selector").val(),
        page: selectedPage,
        checkedLecturers: selectedLecturers,
        uncheckedLecturers: unSelectedLecturers
    }

    fetch('api/filterSubjectHasLecturers.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#lecturers-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const addIntakeToRegisteredStudents = (studentId, intakeId) => {
    ////console.log(studentId, intakeId);
    const data = {
        studentId: studentId,
        intakeId: intakeId,

    }
    fetch('api/addIntakeToRegisteredStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRegisteredStudents(intakeId);
                $("#student-intake-id").val('');
                $("#studentAddIntake").modal('hide');

                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.sIId) {
                $("#student-intake-id").addClass("is-invalid");
                $("#student-intake-id-feedback").text(response.sIId);
            }

        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const filterRegisteredStudents = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    ////console.log(data);

    fetch('api/filterRegisteredStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const uploadStudentBulkToIntake = (id) => {
    toggleLoader(true);
    const formData = new FormData();
    formData.append("intake_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentBulkToIntake.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            $("#studentBulkUpload").modal('hide');
            $("#csv-file-feedback").text('');
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
                $("#csv-file-feedback").text('');
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                $("#csv-file-feedback").text('');
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.file); }, 1000);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const downloadIntakeStudentsCSV = (id) => {
    const modal = new bootstrap.Modal($('#csvDownload')[0]);
    modal.show();

    const waitingMessage = $('#waitingMessage');
    const successMessage = $('#successMessage');

    waitingMessage.show();
    successMessage.hide();

    const data = {
        intake_id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        province: $("#province").val(),
        district: $("#district").val(),
        city: $("#city").val(),
    };

    fetch('api/downloadIntakeStudentsCSV.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => {
            console.log(response);
            const contentType = response.headers.get("content-type");

            // First extract the filename from response headers
            const contentDisposition = response.headers.get('Content-Disposition');
            let fileName = 'students.csv';

            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename="(.+)"/);
                if (filenameMatch && filenameMatch[1]) {
                    fileName = filenameMatch[1];
                }
            }

            if (contentType && contentType.includes("text/csv")) {
                ////console.log("text/csv");
                // Return both blob and filename
                return response.blob().then(blob => ({ blob, fileName }));
            } else {
                ////console.log("erro/csv");
                return response.json();
            }
        })
        .then((data) => {
            ////console.log(data);

            // Check if data contains blob (CSV case)
            if (data.blob && data.blob instanceof Blob) {
                const url = window.URL.createObjectURL(data.blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.fileName; // Use the extracted filename
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                waitingMessage.hide();
                successMessage.show();
            }
            // Handle JSON response (error case)
            else if (data.error) {
                showToast("error", data.error);
                modal.hide();
            } else {
                showToast("error", "An unknown error occurred.");
                modal.hide();
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occurred. Please try again later.");
            modal.hide();
        });
}
const downloadIntakePaymentsReport = (btn, id, intake) => {
    toggleBtnLoader(btn, true);

    const data = {
        intake_id: id,
        text: $("#payment-search-text").val(),
        category: $("#payment-category-selector").val(),
        status: $("#payment-status-selector").val(),
        sort: $("#payment-sort-selector").val(),
    };

    fetch('api/downloadIntakePaymentsReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get("content-type");

            if (contentType && contentType.includes("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")) {
                return response.blob();
            }

            if (contentType && contentType.includes("application/json")) {
                return response.json();
            }

            const text = await response.text();
            console.log(text);
            throw new Error("Unexpected response type.");
        })
        .then((data) => {
            toggleBtnLoader(btn, false);

            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement("a");

                a.href = url;
                a.download = `Payments Report - ${intake}.xlsx`;

                document.body.appendChild(a);
                a.click();
                a.remove();

                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", error.message);
        });
}
const assignLecturerToLateSubmitStudentsAssignments = (id) => {
    const lecturer_id = $("#late-lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleLoader(true);
        const data = {
            assignment_id: id,
            search_text: $("#late-search-text").val(),
            sort: $("#late-sort-selector").val(),
            resultPerPage: $("#late-result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToLateSubmitStudentsAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((response) => response.text())
            .then((data) => {
                ////console.log(data);
                toggleLoader(false);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else if (response.hasOwnProperty('done')) {
                    showToast("success", response.done);
                    filterLateSubmitStudentsAssignments(id);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }


}
const addFAQ = () => {
    toggleLoader(true);
    const data = {
        question: $("#question").val(),
        answer: faqAnswer.getHTMLCode(),
        video_link: $("#video_link").val()
    }
    fetch('api/addFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                $("#addFAQ").modal("hide");
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty('question')) {
                    $("#question-feedback").text(response.question);
                    $("#question").addClass("is-invalid");
                }
                if (response.hasOwnProperty('answer')) {
                    $("#answer-feedback").text(response.answer);
                }
                if (response.hasOwnProperty('link')) {
                    $("#video-link-feedback").text(response.link);
                    $("#video_link").addClass("is-invalid");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const deleteFAQ = (id, status = 1) => {
    toggleLoader(true);
    const data = {
        faq: id
    }
    fetch('api/deleteFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const editFAQ = (id) => {
    toggleLoader(true);
    const data = {
        faq: id,
        question: $("#question").val(),
        answer: faqAnswer.getHTMLCode(),
        video_link: $("#video_link").val()
    }
    fetch('api/editFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                $("#editFAQ").modal("hide");
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty('question')) {
                    $("#question-feedback").text(response.question);
                    $("#question").addClass("is-invalid");
                }
                if (response.hasOwnProperty('answer')) {
                    $("#answer-feedback").text(response.answer);
                }
                if (response.hasOwnProperty('link')) {
                    $("#video-link-feedback").text(response.link);
                    $("#video_link").addClass("is-invalid");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterFAQ = () => {
    const data = {
        text: $("#user-search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    fetch('api/filterFAQ.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#faq-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const cancelAssignment = (id, subjectId) => {

    fetch('api/cancelAssignmnet.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ assignment: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                filterSubjectHasAssignments(subjectId);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const deleteAssignment = (id, subjectId) => {

    fetch('api/deleteAssignment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ assignment: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                filterSubjectHasAssignments(subjectId);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const assignLecturerToStudentsReCorrectionAssignments = (btn, id) => {
    const lecturer_id = $("#recorrection-lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleBtnLoader(btn, true);
        const data = {
            assignment_id: id,
            search_text: $("#recorrection-search-text").val(),
            sort: $("#recorrection-sort-selector").val(),
            resultPerPage: $("#recorrection-result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToStudentsReCorrectionAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    filterRecorrectionRequestStudentsAssignments(id);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }
}

const filterRecorrectionRequestStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        filter: $("#recorrection-filter-selector").val(),
        sort: $("#recorrection-sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterRecorrectionRequestStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-recorrection-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateAssignmentRecorrectionPaymentStatus = (paymentId) => {
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateAssignmentRecorrectionPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    // Show a success toast.
                    $("#status-selector-feedback").text(response.status);
                    // Reload the page after 4 seconds.
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    // Show an error toast.
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const addRecorrectionAssignmentMark = (btn, id) => {
    toggleBtnLoader(btn, true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRecorrectionRequestStudentsAssignments(id);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editRecorrectionAssignmentMark = (id, assignmentId) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRecorrectionRequestStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}


const addLecturerRecorrectionAssignmentMark = (id) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPRecorrectionRequestStudentsAssignments(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const editLecturerRecorrectionAssignmentMark = (id, assignmentId) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPRecorrectionRequestStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const uploadRecorrectionAssignmentsMarksBulk = (btn, id) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("assignment_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadRecorrectionAssignmentsMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.done);
            } else {
                toggleBtnLoader(btn, false);
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const openMarksReleasedModal = (id) => {
    toggleLoader(true);
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
    }

    fetch('api/getMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.modal) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseAssignmentMarks = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
    }

    fetch('api/releaseAssignmentMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#markReleasedModal").modal('hide');
                showToast("success", response.done);
                filterStudentsAssignments(id);

            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const openRecorrectionMarksReleasedModal = (id) => {
    toggleLoader(true);
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/getRecorrectionMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.modal) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseRecorrectionAssignmentMarks = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/releaseRecorrectionAssignmentMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#markReleasedModal").modal('hide');
                showToast("success", response.done);
                filterStudentsAssignments(id);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLPRecorrectionRequestStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        filter: $("#recorrection-filter-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLPRecorrectionRequestStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-recorrection-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLPStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLPStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPhysicalExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addPhysicalExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const addOnlineExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addOnlineExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editPhysicalExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addPhysicalExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editOnlineExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addOnlineExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const openExamMarksReleasedModal = (id) => {
    const data = {
        examId: id,
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/getExamMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('modal')) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseExamMarks = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        resultPerPage: $("#show-result-selector").val(),

    }

    fetch('api/releaseExamMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#markReleasedModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadExamMarksBulk = (id) => {
    ////console.log(id);
    toggleLoader(true);
    const formData = new FormData();
    formData.append("exam_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadExamMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(id);
                $("#file-selector-input").val('');
                $("#marksBulkUpload").modal('hide');
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsExaminations = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsExaminations.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-examination-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsQuizzes = (id) => {
    const data = {
        quizId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsQuizzes.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-quiz-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const openQuizMarksReleasedModal = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/getQuizMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('modal')) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseQuizMarks = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/releaseQuizMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#markReleasedModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsQuizzes(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const facultiesPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/facultiesPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    showToast("error", "An error occured. Please try again later.");
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserFacultiesPermission = (btn) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserFacultiesPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterFacultiesPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterFacultiesPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeRequestStudentPayment = (id) => {
    fetch('api/removeRequestStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ paymentId: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateStudentPayment = (btn, id) => {
    toggleBtnLoader(btn, true);

    const data = {
        paymentId: id,
        paymentType: $("#payment-type").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        paymentNote: $("#payment-note").val(),
        dueDate: $("#due-date").val(),
        enablePenalty: $("#enable-panelty").prop('checked'),
        penalty: getInstallmentPenalty()
    }

    const validationMap = {
        paymentType: ["#payment-type", "#payment-type-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        paymentNote: ["#payment-note", "#payment-note-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
        enablePenalty: ["#enable-panelty", "#enable-panelty-feedback"],
        penaltyOption: ["#panelty-option", "#panelty-option-feedback"],
    };

    fetch('api/updateStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                applyValidation(response, validationMap);
            }
        })
}

const loadAvailableUserPaymentsPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserPaymentsPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const paymentsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/paymentsPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterPaymentsPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPaymentsPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const lectureSchedulePermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/lectureSchedulePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadAvailableUserLectureSchedulePermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLectureSchedulePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const lectureRecordingsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/lectureRecordingsPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserLectureRecordingPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLectureRecordingPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const filterPortalPayments = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterPortalPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const addPortalPayment = (studentId) => {
    const data = {
        studentId: studentId
    }

    fetch('api/addPortalPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterPortalPaymentAddStudents();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateStudentPortalPaymentStatus = (paymentId, status) => {
    const data = {
        paymentId: paymentId,
        status: status
    }

    fetch('api/updateStudentPortalPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsPortalPayments();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterStudentsPortalPayments = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        page: selectedPage,
        selectPayment: selectedUsers,
        unSelectPayment: unSelectedUsers,
    }

    ////console.log($("#update-selector").val())
    ////console.log(selectedUsers);

    fetch('api/filterStudentsPortalPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateStudentsPortalPaymentBulk = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        currentPage: selectedPage,
        page: selectedPage,
        selectPayments: selectedUsers,
        unSelectPayments: unSelectedUsers,
    }
    ////console.log($("#update-selector").val());
    fetch('api/updateStudentsPortalPaymentBulk.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                selectedUsers = [];
                unSelectedUsers = [];
                filterStudentsPortalPayments();

            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const uploadStudentPortalPaymentBulk = () => {
    const formData = new FormData();
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentPortalPaymentBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterPortalPaymentAddStudents();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const markAttendance = (id, lectureId, status, type = 1) => {
    const data = {
        studentId: id,
        lectureId: lectureId,
        attendStatus: status
    }

    ////console.log(data);

    fetch('api/markAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                if (type == 1) {
                    filterStudentsOngoingLectureAttendance(lectureId);
                } else {
                    filterSubjectLectureAttendance(lectureId);
                }

            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateAttendance = (id, lectureId, type = 1) => {
    const data = {
        attendanceId: id
    }

    ////console.log(data);

    fetch('api/updateAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                if (type == 1) {
                    filterStudentsOngoingLectureAttendance(lectureId);
                } else {
                    filterSubjectLectureAttendance(lectureId);
                }
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterStudentsOngoingLectureAttendance = (lectureId) => {
    const data = {
        lecture_id: lectureId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    ////console.log($("#update-selector").val())
    ////console.log(selectedUsers);

    fetch('api/filterStudentsOngoingLectureAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const editIntakeStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("siid", $("#siid").val());
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("counselor", $("#user").val() || 0);
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    appendStudentDocumentsToFormData(form);


    const physicalDocs = [];
    document.querySelectorAll(".physical-doc-checkbox-new:checked").forEach((cb) => physicalDocs.push(cb.value));
    form.append("physical_documents", JSON.stringify(physicalDocs));

    // Signing dates for date-required documents (e.g. agreement_date).
    // Always sent; the API only reads a date when the document is ticked or a file is uploaded.
    const signingDateMap = {};
    document.querySelectorAll(".physical-doc-date").forEach((input) => {
        const field = input.dataset.field; // e.g. "agreement_date"
        form.append(field, input.value);
        signingDateMap[field] = ["#" + input.id, "#doc-" + field.replace(/_/g, "-") + "-feedback"];
    });


    const validationMap = {
        siid: ["#siid", "#siid-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        counselor: ["#user", "#user-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        ol_result_sheet: ["#doc-ol-result-sheet", "#doc-ol-result-sheet-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
        grama_niladhari: ["#doc-grama-niladhari", "#doc-grama-niladhari-feedback"],
        guardian_nic: ["#doc-guardian-nic", "#doc-guardian-nic-feedback"],
        nic_size_photos: ["#doc-nic-size-photos", "#doc-nic-size-photos-feedback"],
        student_photo: ["#doc-student-photo", "#doc-student-photo-feedback"],
        agreement: ["#doc-agreement", "#doc-agreement-feedback"],
        ...signingDateMap,
    };
    fetch("api/editIntakeStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectPreRecordedLectures = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterSubjectPreRecordedLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectRecordings = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterSubjectRecordings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const applySelectedSubjectStudentsUpdate = (subject, update) => {
    if (update != 0) {
        const data = {
            subjectId: subject,
            update: update,
            selectStudents: selectedUsers,
            unSelectStudents: unSelectedUsers
        }
        fetch('api/updateSubjectStudents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectStudents(subject);
                    setDefault();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addLecturerLecturePayment = (btn, lectureId) => {
    toggleBtnLoader(btn, true);
    const data = {
        lectureId: lectureId,
        startTime: $('#start-time').val(),
        endTime: $('#end-time').val(),

    }
    fetch('api/addLecturerLecturePayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(res => res.json())
        .then(response => {
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    start: ["#start-time", "#start-time-feedback"],
                    end: ["#end-time", "#end-time-feedback"],
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLecturerLecturePayment = (id, status) => {
    toggleLoader(true)
    const data = {
        lecturerPayemntId: id,
        paymentStatus: status
    }
    fetch('api/updateLecturerLecturePayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const addLecturerAssignmentPayment = (lecturerId, assignemntId) => {
    toggleLoader(true)
    const data = {
        lecturerId: lecturerId,
        assignmentId: assignemntId
    }
    ////console.log(lecturerId, assignemntId);
    fetch('api/addLecturerAssignmentPayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const updateLecturerAssignmentPayment = (id, status) => {
    toggleLoader(true)
    const data = {
        lecturerPayemntId: id,
        paymentStatus: status
    }
    fetch('api/updateLecturerAssignmentPayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const payoutLecturerPayments = () => {
    let payoutsPayment = [];
    collectedItems.forEach(item => {
        payoutsPayment.push({
            lecturerId: item.lecturersId,
            amount: item.amount,
            count: item.count,
            type: item.type
        });
    });

    ////console.log(payoutsPayment);

    const data = {
        payoutsPayment: payoutsPayment
    }
    fetch('api/payoutLecturerPayments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });

}

const updatelecturerPayoutRequestStatus = (id) => {
    const data = {
        payoutId: id,
        payoutStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateLecturerPayoutRequestStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const setupUserPassword = (token, status) => {
    const data = {
        token: token,
        status: status,
        password: $("#password").val()
    }

    fetch('api/setupUserPassword.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            $("#codeError").html('');
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location = "https://dashboard.orinstolms.com/"; }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                if (response.hasOwnProperty("code")) {
                    $("#codeError").html(response.code);
                }
            }
        })
}

const forgottenPassword = () => {
    toggleLoader(true);
    const data = {
        email: $("#email").val()
    }

    fetch('api/forgottenPassword.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('email')) {
                if (response.hasOwnProperty('email')) {
                    $("#email").addClass("is-invalid");
                    $("#email-feedback").html(response.email);
                } else {
                    $("#email").removeClass("is-invalid");
                    $("#email-feedback").html("");
                }
            }
        })
        .catch((error) => {

            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}
const approveQuizResubmitRequest = (request_id, quizId) => {
    fetch("api/approveQuizResubmitRequest.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ request_id })
    })
        .then(res => res.json())
        .then(response => {
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsQuizResetRequests(quizId);
            }
        }).catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const filterStudentsQuizResetRequests = (id) => {
    const data = {
        quizId: id,
        text: $("#search-text")?.val(),
        filter: $("#filter-selector")?.val(),
        sort: $("#sort-selector")?.val(),
        resultPerPage: $("#show-result-selector")?.val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsQuizResetRequests.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-quiz-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const removeLectureAttachment = (id) => {
    const data = {
        id: id,
    }
    fetch('api/removeLectureAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                if (attachmentDiv) {
                    attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                    attachmentDiv.style.opacity = 0;
                    attachmentDiv.style.transform = "scale(0.95)";
                    setTimeout(() => attachmentDiv.remove(), 500);
                }
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editStudentPaymentPlan = (planId) => {
    toggleLoader(true);
    const data = {
        planId: planId,
        paymentCategoryId: $("#payment-category")?.val(),
        paymentPlanId: $("#payment-plan")?.val(),
        paymentAmount: $("#payment-amount")?.val(),
        discountType: $("#discount-type")?.val(),
        discountAmount: $("#discount")?.val(),
        paymentPeriod: $("#period")?.val(),
        discountNote: $("#discount-note")?.val(),
    }

    fetch('api/editStudentPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                if (response.hasOwnProperty('paymentCategory')) {
                    $("#payment-category").addClass("is-invalid");
                    $("#payment-category-feedback").text(response.paymentCategory);
                } else {
                    $("#payment-category").removeClass("is-invalid");
                    $("#payment-category-feedback").text("");
                }

                if (response.hasOwnProperty('paymentPlan')) {
                    $("#payment-plan").addClass("is-invalid");
                    $("#payment-plan-feedback").text(response.paymentPlan);
                } else {
                    $("#payment-plan").removeClass("is-invalid");
                    $("#payment-plan-feedback").text("");
                }

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('discountType')) {
                    $("#discount-type").addClass("is-invalid");
                    $("#discount-type-feedback").text(response.discountType);
                } else {
                    $("#discount-type").removeClass("is-invalid");
                    $("#discount-type-feedback").text("");
                }

                if (response.hasOwnProperty('discountAmount')) {
                    $("#discount").addClass("is-invalid");
                    $("#discount-feedback").text(response.discountAmount);
                } else {
                    $("#discount").removeClass("is-invalid");
                    $("#discount-feedback").text("");
                }
            }
        })
}

const filterSubjectLectureAttendance = (lectureId) => {
    const data = {
        lecture_id: lectureId,
        text: $("#search-text")?.val(),
        sort: $("#sort-selector")?.val(),
        sortByName: sortCondition1,
        sortById: sortCondition2,
        resultPerPage: $("#show-result-selector")?.val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectLectureAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#attendance-table-container").html(data);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const updateInquiryStatus = (id) => {
    toggleLoader(true);
    const data = {
        inquiryId: id,
        inquiryStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateInquiryStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updateInquiryStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const filterInquiries = () => {

    const data = {
        text: $("#user-search-text").val(),
        category: $("#category-selector").val(),
        status: $("#status-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    console.log(data);

    fetch(`api/filterInquiries.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#inquiries-table-container").html(data);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const assignUserToInquiry = (id) => {
    toggleLoader(true);
    const data = {
        inquiry: id,
        user: $("#userHidden").val(),
    }

    fetch('api/assignUserToInquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#assignUser").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('user')) {
                    $("#user-feedback").text(response.user);
                } else {
                    $("#user-feedback").text("");
                }

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLectureRecordings = () => {
    const data = {
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        level: $("#level-selector").val(),
        subject: $("#subject-selector").val(),
        lecturer: $("#lecturer-selector").val(),
        lec_type: $("#type-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterLectureRecordings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.history.back() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterReCorrectionAssignmentLecturersPayments = (id) => {
    const data = {
        id: id,
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterReCorrectionAssignmentLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersPayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterAssignmentLecturersPayments = (id) => {
    const data = {
        id: id,
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterAssignmentLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersAssignmentsPayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersAssignmentsPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersLecturePayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersLecturePayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersPayoutRequest = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersPayoutRequest.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterPortalSubscribedStudents = () => {
    const data = {
        text: $("#search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPortalSubscribedStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
                getTableWidth('#students-table', '#pagination-container');
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterLectureSchedulePermissions = () => {
    console.log(selectedPermissions);
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLectureSchedulePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const getLiveSearchResults = () => {
    const searchText = $("#live-search-input").val();
    fetch(`api/getLiveSearchResults.php?text=${searchText}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    renderSearchHistory();
                }
            } catch (e) {
                $("#live-search-results").html(data);
                initializeLanguage();
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaneltyPayment = (paymentId) => {
    toggleLoader(true);
    const data = {
        paymentId: paymentId,
        paymentAmount: $("#payment-amount").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
    }

    fetch('api/addPaneltyPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaneltyPayment = (paneltyId) => {

    toggleLoader(true);
    const data = {
        paneltyId: paneltyId,
        paymentAmount: $("#payment-amount").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
    }

    fetch('api/editPaneltyPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updatePortalPaymentStatus = (paymentId) => {
    toggleLoader(true);
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    console.log(data);

    fetch('api/updatePortalPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again.");
        });
}

const addGradeCalculationStructure = (btn, subject) => {
    toggleBtnLoader(btn, true);
    const gradeType = $("#grade-type").val();
    let gradeTypeData = [];

    if (gradeType == 2) {
        gradeTypeData = getCategoryGradeValues();
    } else if (gradeType == 4) {
        gradeTypeData = getRubricData();
    }

    const data = {
        subject: subject,
        gradeScales: getGradeScalesData(),
        gradeType: gradeType,
        gradeTypeData: gradeTypeData
    };

    fetch('api/addGradeCalculationStructure.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            console.log(response);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                let y = 10;

                doc.setFontSize(16);

                if (response.grade_scales) {

                    const gradeScaleBody = Object.entries(response.grade_scales).map(([key, msg]) => [
                        "Row " + (parseInt(key) + 1),
                        msg
                    ]);

                    doc.autoTable({
                        startY: y,
                        theme: "grid",
                        head: [["Grade Scale Row", "Error Message"]],
                        body: gradeScaleBody,
                        styles: { fontSize: 11 },
                        headStyles: { fillColor: [67, 160, 71] }
                    });

                }

                if (response.rubric) {

                    Object.keys(response.rubric).forEach(rIndex => {
                        const rubric = response.rubric[rIndex];

                        const rows = [];

                        if (rubric.rubricName != null && String(rubric.rubricName).trim() !== "") {
                            rows.push(["Rubric Name", rubric.rubricName]);
                        }

                        if (rubric.rubricWeight != null && String(rubric.rubricWeight).trim() !== "") {
                            rows.push(["Rubric Weight", rubric.rubricWeight]);
                        }

                        if (rows.length > 0) {
                            doc.autoTable({
                                startY: y,
                                theme: "grid",
                                head: [["Rubric " + rIndex, "Error Message"]],
                                body: rows,
                                styles: { fontSize: 11 },
                                headStyles: { fillColor: [30, 136, 229] }
                            });
                            y = doc.lastAutoTable.finalY + 10;
                        }

                        Object.keys(rubric.criteria).forEach(cIndex => {
                            const criteria = rubric.criteria[cIndex];

                            let colname = "Rubric " + rIndex + " - Criteria " + cIndex;

                            doc.autoTable({
                                startY: y,
                                theme: "grid",
                                head: [[colname, "Error Message"]],
                                body: [
                                    ["Name", criteria.name],
                                    ["Weight", criteria.weight],
                                    ["Max Mark", criteria.maxMark],
                                ],
                                styles: { fontSize: 11 },
                                headStyles: { fillColor: [67, 160, 71] },
                            });

                            y = doc.lastAutoTable.finalY + 12;
                        });
                    });

                }
                const pdfData = doc.output("blob");
                const url = URL.createObjectURL(pdfData);
                window.open(url, "_blank");
            }
        })
        .catch((error) => {
            console.log(error);
            toggleBtnLoader(btn, false);
            showToast("error", "An error occurred. Please try again.");
        });
}

const addPaymentType = (btn, planId) => {
    toggleBtnLoader(btn, true);
    const data = {
        planId: planId,
        name: $("#type-name").val(),
    }

    const validationMap = {
        name: ["#type-name", "#type-feedback"],
    };


    fetch('api/addPaymentType.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                $("#addPaymentType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaymentType = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        name: $("#edit-type-name").val(),
    }

    const validationMap = {
        name: ["#edit-type-name", "#edit-type-feedback"],
    };


    fetch('api/editPaymentType.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#editPaymentType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaneltyPaymentCategory = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        option: $("#option").val(),
        type: $("#type").val(),
        currency: $("#currency").val(),
        amount: $("#panelty-amount").val(),
        days: $("#panelty-days").val()
    }

    const validationMap = {
        option: ["#option", "#option-feedback"],
        type: ["#type", "#amount-type-feedback"],
        currency: ["#currency", "#currency-feedback"],
        amount: ["#panelty-amount", "#amount-feedback"],
        days: ["#panelty-days", "#days-feedack"]
    };


    fetch('api/addPaneltyPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {

            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#addPaneltyType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaneltyPaymentCategory = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        option: $("#edit-option").val(),
        type: $("#edit-type").val(),
        currency: $("#edit-currency").val(),
        amount: $("#edit-panelty-amount").val(),
        days: $("#edit-panelty-days").val()
    }

    const validationMap = {
        option: ["#edit-option", "#edit-option-feedback"],
        type: ["#edit-type", "#edit-amount-type-feedback"],
        currency: ["#edit-currency", "#edit-currency-feedback"],
        amount: ["#edit-panelty-amount", "#edit-amount-feedback"],
        days: ["#edit-panelty-days", "#edit-days-feedack"]
    };

    fetch('api/editPaneltyPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#editPaneltyType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaymentGroup = (btn, intake_id) => {
    toggleBtnLoader(btn, true);
    const data = {
        intake_id: intake_id,
        name: $("#group-name").val(),
    }

    const validationMap = {
        name: ["#group-name", "#group-name-feedback"]
    };

    fetch('api/addPaymentGroup.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#addPaymentGroup").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const duplicateIntake = (intake_id) => {
    toggleLoader(true);
    const data = {
        intake_id: intake_id,
    }

    fetch('api/duplicateIntake.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaymentIntakePaymentGroup = (btn, group) => {
    toggleBtnLoader(btn, true);
    const data = {
        group: group,
        category: $("#payment-category").val(),
        type: $("#payment-type").val(),
        plan: $("#payment-plan").val(),
        amount: $("#amount").val(),
        discountType: $("#discount-type").val(),
        discount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        dueDate: $("#due-date").val(),
        isEnablePanelty: $("#enable-panelty").prop('checked'),
        paneltyOption: $("#panelty-option").val(),
        paneltyCategories: selectedCategories
    }

    const validationMap = {
        category: ["#payment-category", "#payment-category-feedback"],
        type: ["#payment-type", "#payment-type-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
        amount: ["#amount", "#amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discount: ["#discount", "#discount-feedback"],
        discountNote: ["#discount-note", "#discount-note-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
        paneltyOption: ["#panelty-option", "#panelty-option-feedback"]
    }

    fetch('api/addPaymentIntakePaymentGroup.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation("", validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const loadPaymentType = () => {
    const plan = $("#payment-plan").val();

    fetch(`api/loadPaymentType.php?id=${plan}`)
        .then(res => res.json())
        .then(response => {
            if (response.error) {
                showToast("error", response.error);
                $("#payment-type").prop("disabled", true);
                return;
            }

            if (response.plan) {
                $("#payment-plan").addClass("is-invalid");
                $("#payment-plan-feedback").text(response.plan);
                $("#payment-type").prop("disabled", true).html("<option value='0'>- Select -</option>");
                return;
            }



            if (response.types.length != 0) {
                // Clear validation
                $("#payment-plan").removeClass("is-invalid");
                $("#payment-plan-feedback").text("");

                // Populate payment types
                let options = `<option value="0">- Select -</option>`;
                response.types.forEach(type => {
                    options += `<option value="${type.payment_type_id}">${type.payment_type}</option>`;
                });

                $("#payment-type")
                    .html(options)
                    .prop("disabled", false);
                $("#fees-type").hasClass('d-none') ? $("#fees-type").removeClass('d-none') : "";
            } else {
                $("#plan-feedback").text("No payment type found for this plan.");
            }

            console.log(response.types.length);
            planHasPaymentTypes = response.types;
            maximumAllowedPayments = response.types.length;
            if (response.types.length > 1) {
                $("#add-new-payment-area").hasClass('d-none') ? $("#add-new-payment-area").removeClass('d-none') : "";
            } else {
                $("#add-new-payment-area").hasClass('d-none') ? '' : $("#add-new-payment-area").addClass('d-none');
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadPaymentPlanHasStudents = (intakeId) => {
    const data = {
        id: intakeId,
        plan: $("#payment-plan").val(),
        category: $("#payment-category").val()
    }
    fetch('api/loadPaymentPlanHasStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#student-list-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntakeStudentsToPayments = (btn, id) => {
    const data = {
        id: id,
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        payments: collectInstallmentData(),
        checkedStudents: selectedUsers,
        unCheckedStudents: unSelectedUsers,
        checkAll: $("#check-all").prop('checked')
    }

    fetch('api/addIntakeStudentsToPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
            if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }

            if (response.installments) {
                toggleBtnLoader(btn, false);
                generatePaymentErrorPDF(response);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });

}

const filterPaymentCategoryStudents = (id) => {
    const data = {
        id: id,
        category: $("#payment-category").val(),
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterPaymentCategoryStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
                $("#student-list-container").hasClass('d-none') ? $("#student-list-container").removeClass('d-none') : "";

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const resendUserRegistrationEmail = (user) => {
    toggleLoader(true);
    fetch('api/resendUserRegistrationEmail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user: user })
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addLead = (btn) => {
    toggleBtnLoader(btn, true);

    const data = {
        full_name: $("#full-name").val(),
        dob: $("#dob").val(),
        gender: $("#gender-selector").val(),
        address: $("#address").val(),
        email: $("#email").val(),
        mobile: $("#mobile").val(),
        whatsapp: $("#whatsapp").val(),

        school: $("#school").val(),
        grade: $("#grade").val(),
        college: $("#college").val(),

        programme_id: $("#programe-selector").val(),
        lead_source_id: $("#source-selector").val(),
        institute: $("#institute-selector").val(),
        institute_branch: $("#institute-branch-selector").val(),
        user_id: $("#user-selector").val(),

        note: $("#note").val()
    };

    const validationMap = {
        full_name: ["#full-name", "#fullname-feedback"],
        dob: ["#dob", "#dob-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        address: ["#address", "#address-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],

        school: ["#school", "#school-feedback"],
        grade: ["#grade", "#grade-feedback"],
        college: ["#college", "#college-feedback"],

        programme_id: ["#programe-selector", "#programme-feedback"],
        lead_source_id: ["#source-selector", "#source-feedback"],
        institute: ["#institute-selector", "#institute-feedback"],
        institute_branch: ["#institute-branch-selector", "#institute-branch-feedback"],
        user_id: ["#user-selector", "#user-feedback"],

        note: ["#note", "#note-feedback"]
    };
    fetch("api/addLead.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast('success', response.done);
                setTimeout(() => { window.location = 'new-leads'; }, 3000);
            } else if (response.error) {
                showToast('error', response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.log("Error:", error);
        });
}

const uploadLeadBulk = (btn) => {
    toggleBtnLoader(btn, true);
    const preventDuplicates =
        document.getElementById("preventDuplicateCheckbox").checked;

    const formData = new FormData();
    formData.append("file", $("#file-selector-input")[0].files[0]);
    formData.append("prevent_duplicates", preventDuplicates ? 1 : 0);
    fetch("api/uploadLeadBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                $("#csv-file-feedback").text('');
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.file);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                showToast("error", "An error occured.Please check error log and try again.");
                setTimeout(() => toggleBtnLoader(btn, false), 4000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 3000);
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 4000);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLead = (btn, id) => {
    toggleBtnLoader(btn, true);

    const data = {
        id: id,
        full_name: $("#full-name").val(),
        dob: $("#dob").val(),
        gender: $("#gender-selector").val(),
        address: $("#address").val(),
        email: $("#email").val(),
        mobile: $("#mobile").val(),
        whatsapp: $("#whatsapp").val(),

        school: $("#school").val(),
        grade: $("#grade").val(),
        college: $("#college").val(),

        programme_id: $("#programe-selector").val(),
        lead_source_id: $("#source-selector").val(),
        institute: $("#institute-selector").val(),
        institute_branch: $("#institute-branch-selector").val(),
        user_id: $("#user-selector").val(),

        note: $("#note").val()
    };

    const validationMap = {
        full_name: ["#full-name", "#fullname-feedback"],
        dob: ["#dob", "#dob-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        address: ["#address", "#address-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],

        school: ["#school", "#school-feedback"],
        grade: ["#grade", "#grade-feedback"],
        college: ["#college", "#college-feedback"],

        programme_id: ["#programe-selector", "#programme-feedback"],
        lead_source_id: ["#source-selector", "#source-feedback"],
        institute: ["#institute-selector", "#institute-feedback"],
        institute_branch: ["#institute-branch-selector", "#institute-branch-feedback"],
        user_id: ["#user-selector", "#user-feedback"],

        note: ["#note", "#note-feedback"]
    };
    fetch("api/editLead.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast('success', response.done);
                setTimeout(() => { window.location = './new-leads'; }, 3000);
            } else if (response.error) {
                showToast('error', response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.log("Error:", error);
        });
}

const deleteLead = (id) => {
    toggleLoader(true);
    fetch("api/deleteLead.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            id: id,
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.done) {
                setTimeout(() => { showToast('success', response.done); }, 500);
                setTimeout(() => { window.location.reload(); }, 3500);
            } else {
                setTimeout(() => { showToast('error', response.error); }, 500);
            }
        })
        .catch((error) => {
            console.log("Error:", error);
        });
}

const filterNewLeads = () => {
    const data = {
        text: $("#search-text").val(),
        agent: $("#agent-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterNewLeads.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#leads-table-container").html(data);
                initializeLeads();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateLead = (btn, id) => {
    toggleBtnLoader(btn, true);
    const status = $("#new-status").val() || 0;
    const cdate = $("#cdate").val();
    const ctime = $("#ctime").val();
    const noteHtml = $("#note").val();

    const data = {
        lead: id,
        status: status,
        cdate: cdate,
        ctime: ctime,
        note: noteHtml,
    }

    fetch('api/updateLead.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                $("#updateLead").modal("hide");
                showToast('success', "Lead Updated Successfully");
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                if (response.note) {
                    $("#note-feedback").html(response.note);
                } else {
                    $("#note-feedback").html("");
                }

                if (response.status) {
                    $("#new-status").addClass("is-invalid");
                    $("#status-feedback").html(response.status);
                } else {
                    $("#new-status").removeClass("is-invalid");
                    $("#status-feedback").removeClass("invalid-feedback").html("");
                }

                if (response.cdate || response.ctime) {
                    $("#cdate").addClass("is-invalid");
                    $("#cdate-feedback").html(response.cdate);
                    $("#ctime").addClass("is-invalid");
                    $("#ctime-feedback").html(response.ctime);
                } else {
                    $("#cdate").removeClass("is-invalid");
                    $("#cdate-feedback").removeClass("invalid-feedback").html("");
                    $("#ctime").removeClass("is-invalid");
                    $("#ctime-feedback").removeClass("invalid-feedback").html("");
                }
            }
        })
        .catch((error) => {
            toggleLoader(false);
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });

}

const filterAllLeads = () => {
    const data = {
        text: $("#search-text").val(),
        agent: $("#agent-selector").val(),
        sort: $("#status-selector").val(),
        source: $("#source-selector").val(),
        regStatus: $("#reg-status-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterAllLeads.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#leads-table-container").html(data);
                initializeLeads();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const addComplain = (btn) => {
    toggleBtnLoader(btn, true);
    var intake = $("#intake").val();
    var rnumber = $("#rnumber").val();
    var name = $("#name").val();
    var cnumber = $("#cnumber").val();
    var complain = $("#complain").val();
    var agent = $("#agent").val();
    fetch("api/addComplain.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            intake: intake,
            rnumber: rnumber,
            name: name,
            cnumber: cnumber,
            complain: complain,
            agent: agent,
        }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleBtnLoader(btn, false);
            var response = JSON.parse(data);
            if (response.done) {
                $("#addComplains").modal('hide');
                showToast('success', response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast('error', response.error);
            } else {
                if (response.intake) {
                    $("#intake").addClass("is-invalid");
                    $("#intake-feedback").addClass("invalid-feedback").html(response.intake);
                } else {
                    $("#intake").removeClass("is-invalid").addClass("is-valid");
                    $("#intake-feedback").removeClass("invalid-feedback").html("");
                }
                if (response.rnumber) {
                    $("#rnumber").addClass("is-invalid");
                    $("#rnumber-feedback").addClass("invalid-feedback").html(response.rnumber);
                } else {
                    $("#rnumber").removeClass("is-invalid").addClass("is-valid");
                    $("#rnumber-feedback").removeClass("invalid-feedback").html("");
                }
                if (response.name) {
                    $("#name").addClass("is-invalid");
                    $("#name-feedback").addClass("invalid-feedback").html(response.name);
                } else {
                    $("#name").removeClass("is-invalid").addClass("is-valid");
                    $("#name-feedback").removeClass("invalid-feedback").html("");
                }
                if (response.cnumber) {
                    $("#cnumber").addClass("is-invalid");
                    $("#cnumber-feedback").addClass("invalid-feedback").html(response.cnumber);
                } else {
                    $("#cnumber").removeClass("is-invalid").addClass("is-valid");
                    $("#cnumber-feedback").removeClass("invalid-feedback").html("");
                }
                if (response.complain) {
                    $("#complain").addClass("is-invalid");
                    $("#complain-feedback").addClass("invalid-feedback").html(response.complain);
                } else {
                    $("#complain").removeClass("is-invalid").addClass("is-valid");
                    $("#complain-feedback").removeClass("invalid-feedback").html("");
                }
                if (response.agent) {
                    $("#agent").addClass("is-invalid");
                    $("#agent-feedback").addClass("invalid-feedback").html(response.agent);
                } else {
                    $("#agent").removeClass("is-invalid").addClass("is-valid");
                    $("#agent-feedback").removeClass("invalid-feedback").html("");
                }
            }
        })
        .catch((error) => {
            console.log("Error:", error);
        });
}

const convertLeadToStudent = (btn, leadId) => {

    let regChecked = $("#reg-payment-enabled").is(":checked") ? 1 : 0;

    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lead_id", leadId);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("reg_payment", regChecked);
    form.append("reg_amount", $("#reg-payment-amount").val());
    form.append("custom_reg_amount", $("#custom-reg-payment-amount").val());
    form.append("note", $("#note").val());

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
        reg_amount: ["#reg-amount-input-group", "#reg-payment-amount-feedback"],
        custom_reg_amount: ["#custom_reg-amount-input-group", "#custom_reg-payment-amount-feedback"],

    };

    fetch("api/convertLeadToStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.text())
        .then((response) => {
            console.log(response);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location = "./lead-registered-student-details?lead-id=" + leadId, 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addLeadTarget = (btn, userId) => {
    toggleBtnLoader(btn, true);
    const data = {
        user: userId,
        type: $("#target-type").val(),
        period: $("#target-period").val(),
        intake: $("#intake-id").val(),
        start: $("#start-date").val(),
        end: $("#end-date").val(),
        value: $("#target-value").val()
    }

    const validationMap = {
        type: ["#target-type", "#target-type-feedback"],
        period: ["#target-period", "#target-period-feedback"],
        intake: ["#intake-id", "#intake-id-feedback"],
        start: ["#start-date", "#start-date-feedback"],
        end: ["#end-date", "#end-date-feedback"],
        value: ["#target-value", "#target-value-feedback"],
    }

    fetch("api/addLeadTarget.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#addTarget").modal('hide');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

/* =====================================================================
 * PASTE INTO assets/js/functions.js — REPLACE the existing
 * uploadStudentPaymentReceipt (delete the old one; keeping both causes
 * "Identifier 'uploadStudentPaymentReceipt' has already been declared").
 *
 * Changes:
 *  - sends deactivate_date when the "not paid the full payment" box is shown
 *    (inactive student + partial payment) and shows its error
 *  - discount / penalty fields are optional (sent as 0 when not on the page)
 *  - shows paid date errors (paid_date was missing from the validation map)
 * ===================================================================== */
const uploadStudentPaymentReceipt = (btn, id) => {
    toggleBtnLoader(btn, true);

    const fieldVal = (sel, fallback = "") => ($(sel).length ? $(sel).val() : fallback);
    const deactivateSection = document.getElementById("deactivate-section");
    const needsDeactivateDate = deactivateSection && !deactivateSection.classList.contains("d-none");

    const form = new FormData();
    form.append("payment_id", id);
    form.append("discount_amount", fieldVal("#discount-amount", 0) || 0);
    form.append("penalty_amount", fieldVal("#penalty-amount", 0) || 0);
    form.append("method", $("#payment-method").val());
    form.append("paid_amount", fieldVal("#paid-amount"));
    form.append("paid_date", $("#paid-date").val());
    form.append("note", $("#note").val());
    if (needsDeactivateDate) {
        form.append("deactivate_date", $("#deactivate-date").val());
    }
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("slips[]", files[i]);
    }

    const validationMap = {
        amount: ["#paid-amount", "#paid-amount-feedback"],
        method: ["#payment-method", "#payment-method-feedback"],
        paid_date: ["#paid-date", "#paid-date-feedback"],
        discount: ["#discount-amount", "#discount-amount-feedback"],
        penalty: ["#penalty-amount", "#penalty-amount-feedback"],
        note: ["#note", "#note-feedback"],
        deactivate_date: ["#deactivate-date", "#deactivate-date-feedback"],
    }

    fetch("api/uploadStudentPaymentReceipt.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                toggleBtnLoader(btn, false);
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);

                if (response.slip) {
                    const slipErrorContainer = $("#file-feedback");
                    if (Array.isArray(response.slip)) {
                        const slipArray = response.slip;
                        $("#file-feedback").html("");
                        slipErrorContainer.append('<p class="text-14 text-danger fw-bold">Upload Payment Slip Error Messages : </p>');
                        slipArray.forEach(function (item) {
                            slipErrorContainer.append('<p class="text-12 text-danger">*' + item + '</p>');
                        });
                    } else {
                        slipErrorContainer.append('<p class="text-12 text-danger">*' + response.slip + '</p>');
                    }

                } else {
                    $("#file-feedback").html("");
                }
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editIntakeStudentId = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        siid: $("#siid").val(),
        note: $("#note-siid").val(),
    }

    const validationMap = {
        siid: ["#siid", "#siid-feedback"],
        note: ["#note-siid", "#note-siid-feedback"]
    }

    fetch('api/editIntakeStudentId.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else
                if (response.error) {
                    showToast("error", response.error);
                } else {
                    applyValidation(response, validationMap);
                }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editIntakeStudentDeactiveDate = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        date: $("#inactivate-date").val(),
        note: $("#note-inactive").val(),
    }

    const validationMap = {
        date: ["#inactivate-date", "#date-feedback"],
        note: ["#note-inactive", "#inactive-note-feedback"]
    }

    fetch('api/editIntakeStudentDeactiveDate.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else
                if (response.error) {
                    showToast("error", response.error);
                } else {
                    applyValidation(response, validationMap);
                }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editIntakeStudentAssignedUser = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        user: $("#user").val(),
        note: $("#note").val(),
    }

    const validationMap = {
        user: ["#user", "#user-feedback"]
    }

    fetch('api/editIntakeStudentAssignedUser.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
            if (response.error) {
                showToast("error", response.error);
            }

            if (response.user) {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterDailyWorksLeads = () => {
    const data = {
        text: $("#search-text").val(),
        agent: $("#agent-selector").val(),
        sort: $("#status-selector").val(),
        source: $("#source-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterDailyWorksLeads.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#leads-table-container").html(data);
                initializeLeads();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const removeProgramme = (id) => {
    toggleLoader(true);
    const data = {
        programme: id
    }
    fetch('api/removeProgramme.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeProgrammeConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeIntakeGroup = (id) => {
    toggleLoader(true);
    const data = {
        intake: id
    }
    fetch('api/removeIntakeGroup.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeIntakeGroupConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeFaculty = (id) => {
    toggleLoader(true);
    const data = {
        faculty: id
    }
    fetch('api/removeFaculty.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeFacultyConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const marketingPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/marketingPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserMarketingPermission = (btn) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserMarketingPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}
const filterMarketingPermissions = () => {
    console.log(selectedPermissions);
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterMarketingPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const submitLeaveRequest = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("leave_type", $("#leave-type").val() || 0);
    form.append("day_type", $("#leave-day-type").val() || 0);
    form.append("from_date", $("#from-date").val());
    form.append("to_date", $("#to-date").val());
    form.append("reason", $("#leave-reason").val());
    form.append("attachment", $("#file-selector-input")[0].files[0]);

    const validationMap = {
        leave_type: ['#leave-type', '#leave-type-feedback'],
        day_type: ['#leave-day-type', '#day-type-feedback'],
        from_date: ['#from-date', '#from-date-feedback'],
        to_date: ['#to-date', '#to-date-feedback'],
        reason: ['#leave-reason', '#reason-feedback'],
        attachment: ['', '#attachment-feedback']
    }


    fetch('api/submitLeaveRequest.php', {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLeaveRequestStatus = (btn, requestId) => {
    toggleBtnLoader(btn, true);
    const data = {
        request_id: requestId,
        request_status: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateLeaveRequestStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                $("#updateLeaveStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const filterLeaveRequests = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        type: $("#type-selector").val() || "",
        dayType: $("#day-type-selector").val() || "",
        status: $("#status-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterLeaveRequests.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const filterMyLeaveRequests = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        type: $("#type-selector").val() || "",
        dayType: $("#day-type-selector").val() || "",
        status: $("#status-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterMyLeaveRequests.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const selectLeaveAnalysisUser = (id, name) => {
    document.getElementById("userSearch").value = name;
    document.getElementById("userSuggestions").innerHTML = "";
    fetch('api/getLeaveAnalysisUser.php?id=' + id)
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#leave-usage-table").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
}

const updateLeadProgramme = (btn, lid) => {
    toggleBtnLoader(btn, true);

    const data = {
        lead: lid,
        programme: $("#programme-select").val(),
    };

    const validationMap = {
        programme: ['#programme-select', '#programme-feedback'],
    }

    fetch('api/updateLeadProgramme.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#programmeWarningModal").modal("hide");
                showToast("success", response.done);
                const encodedLid = btoa(lid);
                sendRegistrationLink(encodedLid);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch(error => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const updateLeadStatusAfterLink = (btn, lid) => {

    // ── Validate payment amount if enabled ─────────────────────────
    const paymentEnabled = document.getElementById('regPaymentEnabled').checked;
    const amtInput = document.getElementById('regPaymentAmount');
    const feedback = document.getElementById('regPaymentAmountFeedback');

    feedback.textContent = '';

    if (paymentEnabled) {
        const amount = parseFloat(amtInput.value);
        if (!amtInput.value || isNaN(amount) || amount <= 0) {
            feedback.textContent = 'Please enter a valid registration amount.';
            amtInput.focus();
            return; // stop — do not call the API
        }
    }

    // ── Build payload ───────────────────────────────────────────────
    const data = {
        lead: lid,
        status: 1,
        note: 'Send Registration Link',
    };

    if (paymentEnabled) {
        data.payment_enabled = true;
        data.payment_amount = parseFloat(amtInput.value);
    } else {
        data.payment_enabled = false;
    }

    // ── Send request ────────────────────────────────────────────────
    toggleBtnLoader(btn, true);

    fetch('api/updateLeadRegistrationStatus.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((raw) => {
            toggleBtnLoader(btn, false);
            const response = JSON.parse(raw);

            if (response.hasOwnProperty('error')) {
                showToast('error', response.error);

            } else if (response.hasOwnProperty('done')) {
                showToast('success', response.done);
                copyRegistrationLink();
                $('#registrationLinkModal').modal('hide');
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast('error', 'An error occurred. Please try again later.');
        });
};
const generateDailyAdmissionsReport = (btn) => {

    const fromDate = document.getElementById("dailyAdmissionsReportFromDate").value;
    const toDate = document.getElementById("dailyAdmissionsReportToDate").value;

    if (!fromDate || !toDate) {
        showToast("error", "Please select date range");
        return;
    }

    if (fromDate > toDate) {
        showToast("error", "From date cannot be greater than To date");
        return;
    }

    toggleBtnLoader(btn, true);

    fetch(`api/genarateDailyAdmissionReport.php?from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`)

        .then(async (response) => {

            const contentType = response.headers.get("content-type");

            if (
                contentType &&
                contentType.includes("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
            ) {
                return response.blob();
            }

            if (contentType && contentType.includes("application/json")) {
                return response.json();
            }

            const text = await response.text();
            console.log("Raw response:", text);

            throw new Error("Unexpected response type.");
        })

        .then((data) => {

            toggleBtnLoader(btn, false);

            if (data instanceof Blob) {

                const url = window.URL.createObjectURL(data);
                const a = document.createElement("a");

                a.href = url;
                a.download = `Daily Admissions Report (${fromDate} to ${toDate}).xlsx`;

                document.body.appendChild(a);
                a.click();
                a.remove();

                window.URL.revokeObjectURL(url);

            } else {

                showToast("error", data.error || "Unknown error");
            }
        })

        .catch((error) => {

            toggleBtnLoader(btn, false);

            console.error(error);

            showToast("error", error.message);
        });
};



const generateDailyInquirySummaryReport = (btn) => {

    const fromDate = document.getElementById("dailyInquirySummaryReportFromDate").value;
    const toDate = document.getElementById("dailyInquirySummaryReportToDate").value;

    if (!fromDate || !toDate) {
        showToast("error", "Please select date range");
        return;
    }

    if (fromDate > toDate) {
        showToast("error", "From date cannot be greater than To date");
        return;
    }

    toggleBtnLoader(btn, true);

    fetch(`api/genarateDailyInquirySummaryReport.php?from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`)

        .then(async (response) => {

            const contentType = response.headers.get("content-type");

            if (
                contentType &&
                contentType.includes("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
            ) {
                return response.blob();
            }

            if (contentType && contentType.includes("application/json")) {
                return response.json();
            }

            const text = await response.text();

            console.log(text);

            throw new Error("Unexpected response type.");
        })

        .then((data) => {

            toggleBtnLoader(btn, false);

            if (data instanceof Blob) {

                const url = window.URL.createObjectURL(data);
                const a = document.createElement("a");

                a.href = url;
                a.download = `Daily Inquiry Summary Report (${fromDate} to ${toDate}).xlsx`;

                document.body.appendChild(a);
                a.click();
                a.remove();

                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })

        .catch((error) => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", error.message);
        });
};

const editLeadStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("removed_documents", removedExistingDocuments);

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#gfname", "#gfname-feedback"],
        glname: ["#glname", "#glname-feedback"],
        gemail: ["#gemail", "#gemail-feedback"],
        gmobile: ["#gmobile", "#gmobile-feedback"],
        birth_certificate: ["#doc-birth-certificate", "#doc-birth-certificate-feedback"],
        nic_passport: ["#doc-nic-passport", "#doc-nic-passport-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        certificates: ["#doc-certificates", "#doc-certificates-feedback"],
    };


    fetch("api/editLeadStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}


const updateReservedApplicationStatus = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        updateStatus: $("#status-selector").val(),
        note: $("#note").val(),
        intake: $("#intake-selector").val()
    };
    const validationMap = {
        status: ["#status-selector", "#status-feedback"],
        note: ["#note", "#note-feedback"],
        intake: ["#intake", "#intake-feedback"],
    }

    fetch('api/updateReservedApplicationStatus.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#updateLead").modal('hide');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const assignLeadStudentToIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        intake: $("#intake-selector").val()
    };

    const validationMap = {
        inatke: ['#intake-selector', '#intake-feedback'],
    }

    fetch('api/assignLeadStudentToIntake.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#assignIntake").modal('hide');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLeadTarget = (btn, userId) => {
    toggleBtnLoader(btn, true);

    const data = {
        target_id: $("#edit-target-id").val(),
        user: userId,
        type: $("#edit-target-type").val(),
        period: $("#edit-target-period").val(),
        intake: $("#edit-intake-id").val(),
        start: $("#edit-start-date").val(),
        end: $("#edit-end-date").val(),
        value: $("#edit-target-value").val()
    };

    const validationMap = {
        type: ["#edit-target-type", "#edit-target-type-feedback"],
        period: ["#edit-target-period", "#edit-target-period-feedback"],
        intake: ["#edit-intake-id", "#edit-intake-feedback"],
        start: ["#edit-start-date", "#edit-start-date-feedback"],
        end: ["#edit-end-date", "#edit-end-date-feedback"],
        value: ["#edit-target-value", "#edit-target-value-feedback"],
    };


    fetch("api/editLeadTarget.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(response => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                $("#editTarget").modal('hide');
                showToast("success", response.done);
                filterLeadsTarget(userId);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Something went wrong. Please try again.");
        });
};

const filterLeadsTarget = (id) => {
    const data = {
        user: id,
        text: $("#search-text").val() || "",
        type: $("#type-selector").val() || "",
        period: $("#period-selector").val() || "",
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterLeadsTarget.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#leads-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const filterStudentsPayments = () => {
    const data = {
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        sort: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    console.log(data);
    fetch('api/filterStudentsPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const filterAwaitingPayments = () => {
    const data = {
        text: $("#search-text").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        category: $("#payment-category-selector").val(),
        plan: $("#payment-plan-selector").val(),
        type: $("#payment-type-selector").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition3,
        sortById: sortCondition4,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage2,
    }

    fetch('api/filterAwaitingPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const filterCounsilerPerformance = (id) => {
    const data = {
        user: id,
        text: $("#search-text").val() || "",
        status: $("#status-selector").val() || "",
        source: $("#source-selector").val() || "",
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterCounsilerPerformance.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#leads-table-container").html(result);
                initializeLeads();
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadContractProgrammes = (select) => {
    const row = select.closest(".lecturer-contract-subject-card");
    const faculty = select.value;

    const programmeSelector = row.querySelector(".programme-select");
    const intakeSelector = row.querySelector(".intake-select");
    const levelSelector = row.querySelector(".level-select");
    const subjectSelector = row.querySelector(".subject-select");

    const facultyFeedback = row.querySelector(".faculty-feedback");

    programmeSelector.disabled = true;
    intakeSelector.disabled = true;
    levelSelector.disabled = true;
    subjectSelector.disabled = true;

    programmeSelector.innerHTML = "<option value='0'>- Select Programme -</option>";
    intakeSelector.innerHTML = "<option value='0'>- Select Intake -</option>";
    levelSelector.innerHTML = "<option value='0'>- Select Level -</option>";
    subjectSelector.innerHTML = "<option value='0'>- Select Subject -</option>";

    fetch(`api/loadProgrammes.php?id=${faculty}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);

                if (response.hasOwnProperty("error")) {
                    showToast("error", response.error);
                    programmeSelector.disabled = true;
                } else if (response.hasOwnProperty("faculty")) {
                    programmeSelector.disabled = true;
                    select.classList.add("is-invalid");
                    facultyFeedback.innerText = response.faculty;
                }

            } catch (error) {
                select.classList.remove("is-invalid");
                facultyFeedback.innerText = "";

                programmeSelector.disabled = false;
                programmeSelector.innerHTML = data;
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadContractIntakes = (select) => {
    const row = select.closest(".lecturer-contract-subject-card");
    const programme = select.value;

    const intakeSelector = row.querySelector(".intake-select");
    const levelSelector = row.querySelector(".level-select");
    const subjectSelector = row.querySelector(".subject-select");

    const programmeFeedback = row.querySelector(".programme-feedback");

    intakeSelector.disabled = true;
    levelSelector.disabled = true;
    subjectSelector.disabled = true;

    intakeSelector.innerHTML = "<option value='0'>- Select Intake -</option>";
    levelSelector.innerHTML = "<option value='0'>- Select Level -</option>";
    subjectSelector.innerHTML = "<option value='0'>- Select Subject -</option>";

    fetch(`api/loadIntakes.php?id=${programme}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);

                if (response.hasOwnProperty("error")) {
                    showToast("error", response.error);
                    intakeSelector.disabled = true;
                } else if (response.hasOwnProperty("programme")) {
                    intakeSelector.disabled = true;
                    select.classList.add("is-invalid");
                    programmeFeedback.innerText = response.programme;
                }

            } catch (error) {
                select.classList.remove("is-invalid");
                programmeFeedback.innerText = "";

                intakeSelector.disabled = false;
                intakeSelector.innerHTML = data;
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadContractLevels = (select) => {
    const card = select.closest(".lecturer-contract-subject-card");
    const intake = select.value;

    const levelSelector = card.querySelector(".level-select");
    const subjectSelector = card.querySelector(".subject-select");

    const intakeFeedback = card.querySelector(".intake-feedback");

    levelSelector.disabled = true;
    subjectSelector.disabled = true;

    levelSelector.innerHTML = "<option value='0'>- Select Level -</option>";
    subjectSelector.innerHTML = "<option value='0'>- Select Subject -</option>";

    fetch(`api/loadLevels.php?id=${intake}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);

                if (response.hasOwnProperty("error")) {
                    showToast("error", response.error);
                    levelSelector.disabled = true;
                } else if (response.hasOwnProperty("intake")) {
                    levelSelector.disabled = true;
                    select.classList.add("is-invalid");
                    intakeFeedback.innerText = response.intake;
                }

            } catch (error) {
                select.classList.remove("is-invalid");
                intakeFeedback.innerText = "";

                levelSelector.disabled = false;
                levelSelector.innerHTML = data;
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadContractSubjects = (select) => {
    const card = select.closest(".lecturer-contract-subject-card");
    const level = select.value;

    const subjectSelector = card.querySelector(".subject-select");
    const levelFeedback = card.querySelector(".level-feedback");

    subjectSelector.disabled = true;
    subjectSelector.innerHTML = "<option value='0'>- Select Subject -</option>";

    fetch(`api/loadSubjects.php?id=${level}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);

                if (response.hasOwnProperty("error")) {
                    showToast("error", response.error);
                    subjectSelector.disabled = true;
                } else if (response.hasOwnProperty("level")) {
                    subjectSelector.disabled = true;
                    select.classList.add("is-invalid");
                    levelFeedback.innerText = response.level;
                }

            } catch (error) {
                select.classList.remove("is-invalid");
                levelFeedback.innerText = "";

                subjectSelector.disabled = false;
                subjectSelector.innerHTML = data;
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const createLecturerContract = (btn, lecturerId) => {
    clearLecturerContractErrors();

    const agreementDocument = document.getElementById("agreement-document");

    const formData = new FormData();
    formData.append("lecturer_id", lecturerId);
    formData.append("contract_title", document.getElementById("contract-title").value.trim());
    formData.append("contract_start_date", document.getElementById("contract-start-date").value);
    formData.append("contract_end_date", document.getElementById("contract-end-date").value);
    formData.append("payment_cycle", document.getElementById("payment-cycle").value);
    formData.append("tax_applicable", document.getElementById("tax-applicable").value);
    formData.append("contract_note", document.getElementById("contract-note").value.trim());
    formData.append("subjects", JSON.stringify(collectLecturerContractSubjects()));

    if (agreementDocument.files.length > 0) {
        formData.append("agreement_document", agreementDocument.files[0]);
    }

    toggleBtnLoader(btn, true);

    fetch("api/createLecturerContract.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            if (data.status === "success") {
                showToast("success", data.message);
                setTimeout(() => {
                    window.location.href = "lecturer-contracts?lecturer-id=" + lecturerId;
                }, 800);
                return;
            }

            if (data.status === "validation_error") {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([key, message]) => {
                        if (lecturerContractValidationMap[key]) {
                            const [input, feedback] = lecturerContractValidationMap[key];
                            document.querySelector(input)?.classList.add("is-invalid");
                            document.querySelector(feedback).innerText = message;
                        }
                    });
                }

                if (data.subject_errors) {
                    data.subject_errors.forEach(error => {
                        showSubjectError(error.index, error.input, error.feedback, error.message);
                    });
                }

                showToast("error", "Please check required fields");
                return;
            }

            showToast("error", data.message || "Unable to create lecturer contract");
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
}


const editLecturerContract = (btn, contractId) => {
    clearLecturerContractEditErrors();

    const agreementDocument = document.getElementById("agreement-document");

    const formData = new FormData();
    formData.append("contract_id", contractId);
    formData.append("contract_title", document.getElementById("contract-title").value.trim());
    formData.append("contract_start_date", document.getElementById("contract-start-date").value);
    formData.append("contract_end_date", document.getElementById("contract-end-date").value);
    formData.append("contract_status", document.getElementById("contract-status").value);
    formData.append("payment_cycle", document.getElementById("payment-cycle").value);
    formData.append("tax_applicable", document.getElementById("tax-applicable").value);
    formData.append("contract_note", document.getElementById("contract-note").value.trim());
    formData.append("subjects", JSON.stringify(collectLecturerContractEditSubjects()));

    if (agreementDocument && agreementDocument.files.length > 0) {
        formData.append("agreement_document", agreementDocument.files[0]);
    }

    toggleBtnLoader(btn, true);

    fetch("api/editLecturerContract.php", {
        method: "POST",
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            // ── Success ───────────────────────────────────────────────────────
            if (data.status === "success") {
                showToast("success", data.message);
                setTimeout(() => {
                    window.location.href = "view-lecturer-contract?contract-id=" + contractId;
                }, 800);
                return;
            }

            // ── Validation errors ─────────────────────────────────────────────
            if (data.status === "validation_error") {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([key, message]) => {
                        if (editLecturerContractValidationMap[key]) {
                            const [input, feedback] = editLecturerContractValidationMap[key];
                            document.querySelector(input)?.classList.add("is-invalid");
                            const fb = document.querySelector(feedback);
                            if (fb) fb.innerText = message;
                        }
                    });
                }

                if (data.subject_errors) {
                    data.subject_errors.forEach(error => {
                        showSubjectEditError(
                            error.index,
                            error.input,
                            error.feedback,
                            error.message
                        );
                    });
                }

                showToast("error", "Please check required fields");
                return;
            }

            // ── Generic error ─────────────────────────────────────────────────
            showToast("error", data.message || "Unable to update lecturer contract");
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
};
/* ---------------------------------------------------------------------
 * createPayrollProfile — replace the existing function in functions.js
 * with this one. Only the salary pay date lines are new (marked NEW).
 * ------------------------------------------------------------------- */

// NEW: show server validation errors under the two pay-date fields.
// payrollProfileValidationMap already exists in functions.js; this adds two keys.
if (typeof payrollProfileValidationMap !== "undefined") {
    payrollProfileValidationMap.salary_pay_day = ["#salary-pay-day", "#salary-pay-day-feedback"];
    payrollProfileValidationMap.salary_pay_timing = ["#salary-pay-timing", "#salary-pay-timing-feedback"];
}

function createPayrollProfile(btn, userId) {
    clearPayrollProfileErrors();

    // NEW: clear pay-date errors too (clearPayrollProfileErrors may not know these fields)
    ["salary-pay-day", "salary-pay-timing"].forEach(id => {
        document.getElementById(id)?.classList.remove("is-invalid");
        const fb = document.getElementById(id + "-feedback");
        if (fb) fb.innerText = "";
    });

    // NEW: client-side check for the pay day
    const payDay = document.getElementById("salary-pay-day")?.value || "";
    const payTiming = document.getElementById("salary-pay-timing")?.value || "NEXT_MONTH";
    if (!payDay) {
        document.getElementById("salary-pay-day")?.classList.add("is-invalid");
        const fb = document.getElementById("salary-pay-day-feedback");
        if (fb) fb.innerText = "Select the salary pay day (1 - 31)";
        showToast("error", "Please check required fields");
        return;
    }

    const formData = new FormData();

    formData.append("user_id", userId);
    formData.append("employment_type", document.getElementById("employment-type").value);
    formData.append("epf_no", document.getElementById("epf-no").value);
    formData.append("basic_salary", document.getElementById("basic-salary").value);
    formData.append("epf_employee_rate", document.getElementById("epf-employee-rate").value || 8);
    formData.append("epf_employer_rate", document.getElementById("epf-employer-rate").value || 12);
    formData.append("etf_rate", document.getElementById("etf-rate").value || 3);
    formData.append("is_apit_applicable", document.getElementById("apit-applicable").value || 0);
    formData.append("payment_method", document.getElementById("payment-method").value || "BANK");
    formData.append("bank_name", document.getElementById("bank-name").value.trim());
    formData.append("bank_account_no", document.getElementById("bank-account-no").value.trim());
    formData.append("bank_branch", document.getElementById("bank-branch")?.value.trim() || "");
    formData.append("salary_pay_day", payDay);          // NEW
    formData.append("salary_pay_timing", payTiming);    // NEW
    formData.append("components", JSON.stringify(collectPayrollComponents()));

    toggleBtnLoader(btn, true);

    fetch("api/createPayrollProfile.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            if (data.status === "success") {
                showToast("success", data.message);
                setTimeout(() => {
                    window.history.back();
                }, 3000);
                return;
            }

            if (data.status === "validation_error") {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([key, message]) => {
                        if (!payrollProfileValidationMap[key]) return;

                        const [inputSelector, feedbackSelector] = payrollProfileValidationMap[key];

                        document.querySelector(inputSelector)?.classList.add("is-invalid");

                        const feedback = document.querySelector(feedbackSelector);
                        if (feedback) feedback.innerText = message;
                    });
                }

                if (data.component_errors) {
                    data.component_errors.forEach(error => {
                        showPayrollComponentError(
                            error.index,
                            error.input,
                            error.feedback,
                            error.message
                        );
                    });
                }

                showToast("error", "Please check required fields");
                return;
            }

            showToast("error", data.message || "Unable to save payroll profile");
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
}

function updatePayrollEmployeeDetails(btn, payrollRunEmployeeId) {
    clearPayrollEmployeeErrors();

    const formData = new FormData();
    formData.append("payroll_run_employee_id", payrollRunEmployeeId);
    formData.append("basic_salary", document.getElementById("basic-salary").value);
    formData.append("components", JSON.stringify(collectPayrollEmployeeComponents()));

    toggleBtnLoader(btn, true);

    fetch("api/updatePayrollEmployeeDetails.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            if (data.status === "success") {
                showToast("success", data.message);
                setTimeout(() => window.location.reload(), 3000);
                return;
            }

            if (data.status === "validation_error") {
                if (data.errors?.basic_salary) {
                    document.getElementById("basic-salary").classList.add("is-invalid");
                    document.getElementById("basic-salary-feedback").innerText = data.errors.basic_salary;
                }

                if (data.component_errors) {
                    data.component_errors.forEach(error => {
                        showPayrollEmployeeComponentError(
                            error.index,
                            error.input,
                            error.feedback,
                            error.message
                        );
                    });
                }

                showToast("error", "Please check required fields");
                return;
            }

            showToast("error", data.message || "Unable to update payroll details");
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
}

const genaratePayrollRunsReport = (id, month) => {

    toggleLoader(true);

    fetch(`api/genaratePayrollRunsReport.php?payroll-run-id=${id}`)  // ✅ fixed URL

        .then(async (response) => {

            const contentType = response.headers.get("content-type") || "";

            // ✅ Excel file — download it
            if (contentType.includes("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")) {
                return { type: "blob", data: await response.blob() };
            }

            // ✅ JSON error from PHP
            if (contentType.includes("application/json")) {
                return { type: "json", data: await response.json() };
            }

            // ✅ Catch PHP warnings/errors printed as text/html
            const text = await response.text();
            console.error("Unexpected response from server:", text);
            throw new Error("Server returned an unexpected response. Check console for details.");
        })

        .then(({ type, data }) => {
            toggleLoader(false);

            if (type === "blob") {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement("a");
                a.href = url;
                a.download = `Payroll Runs Report - ${month}.xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

            } else {
                // JSON error response from PHP
                showToast("error", data.error || "Unknown error occurred.");
            }
        })

        .catch((error) => {
            toggleLoader(false);
            console.error("genaratePayrollRunsReport error:", error);
            showToast("error", error.message);
        });
};

const genaratePayrollEmployeePaysheet = (id, name) => {
    window.open(
        `api/genaratePayrollEmployeePaysheet.php?payroll-run-id=${id}`,
        "_blank"
    );
}

const updatePayrollEmployeeStatus = (btn, payrollEmployeeId) => {
    toggleBtnLoader(btn, true);
    const data = {
        payrollEmployeeId: payrollEmployeeId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updatePayrollEmployeeStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePayrollStatusModal").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const updatePayrollRunsStatus = (btn, payrollRunId) => {
    toggleBtnLoader(btn, true);
    const data = {
        payrollRunId: payrollRunId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updatePayrollRunsStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePayrollStatusModal").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }
                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }
            }
        })
}



function editPayrollProfile(btn, profileId) {
    clearPayrollProfileErrors();

    // NEW: clear pay-date errors too
    ["salary-pay-day", "salary-pay-timing"].forEach(id => {
        document.getElementById(id)?.classList.remove("is-invalid");
        const fb = document.getElementById(id + "-feedback");
        if (fb) fb.innerText = "";
    });

    // NEW: pay day is required
    const payDay = document.getElementById("salary-pay-day")?.value || "";
    const payTiming = document.getElementById("salary-pay-timing")?.value || "NEXT_MONTH";
    if (!payDay) {
        document.getElementById("salary-pay-day")?.classList.add("is-invalid");
        const fb = document.getElementById("salary-pay-day-feedback");
        if (fb) fb.innerText = "Select the salary pay day (1 - 31)";
        showToast("error", "Please check required fields");
        return;
    }

    const formData = new FormData();

    formData.append("profile_id", profileId);
    formData.append("employment_type", document.getElementById("employment-type").value);
    formData.append("epf_no", document.getElementById("epf-no").value);
    formData.append("basic_salary", document.getElementById("basic-salary").value);
    formData.append("epf_employee_rate", document.getElementById("epf-employee-rate").value || 8);
    formData.append("epf_employer_rate", document.getElementById("epf-employer-rate").value || 12);
    formData.append("etf_rate", document.getElementById("etf-rate").value || 3);
    formData.append("is_apit_applicable", document.getElementById("apit-applicable").value || 0);
    formData.append("payment_method", document.getElementById("payment-method").value || "BANK");
    formData.append("bank_name", document.getElementById("bank-name").value.trim());
    formData.append("bank_account_no", document.getElementById("bank-account-no").value.trim());
    formData.append("bank_branch", document.getElementById("bank-branch")?.value.trim() || "");
    formData.append("salary_pay_day", payDay);          // NEW
    formData.append("salary_pay_timing", payTiming);    // NEW
    formData.append("components", JSON.stringify(collectPayrollComponents()));

    toggleBtnLoader(btn, true);

    fetch("api/editPayrollProfile.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            if (data.status === "success") {
                showToast("success", data.message);
                setTimeout(() => {
                    window.history.back();
                }, 3000);
                return;
            }

            if (data.status === "validation_error") {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([key, message]) => {
                        if (!payrollProfileValidationMap[key]) return;

                        const [inputSelector, feedbackSelector] = payrollProfileValidationMap[key];

                        document.querySelector(inputSelector)?.classList.add("is-invalid");

                        const feedback = document.querySelector(feedbackSelector);
                        if (feedback) feedback.innerText = message;
                    });
                }

                if (data.component_errors) {
                    data.component_errors.forEach(error => {
                        showPayrollComponentError(
                            error.index,
                            error.input,
                            error.feedback,
                            error.message
                        );
                    });
                }

                showToast("error", "Please check required fields");
                return;
            }

            showToast("error", data.message || data.error || "Unable to save payroll profile");
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
}

const addLeave = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();

    form.append("user", selectedAddLeaveUser || 0);
    form.append("leave_type", $("#leave-type").val() || 0);
    form.append("day_type", $("#leave-day-type").val() || 0);
    form.append("from_date", $("#from-date").val());
    form.append("to_date", $("#to-date").val());
    form.append("reason", $("#leave-reason").val());
    form.append("attachment", $("#file-selector-input")[0].files[0]);

    const validationMap = {
        leave_type: ['#leave-type', '#leave-type-feedback'],
        day_type: ['#leave-day-type', '#day-type-feedback'],
        from_date: ['#from-date', '#from-date-feedback'],
        to_date: ['#to-date', '#to-date-feedback'],
        reason: ['#leave-reason', '#reason-feedback'],
        attachment: ['', '#attachment-feedback'],
        user: ['#userSearch', '#user-feedback']
    }


    fetch('api/addLeave.php', {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const addIntakePaymentPlan = (btn, id, confirmation = null) => {
    const data = {
        id: id,
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
        confirmation: confirmation

    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((data) => {
            console.log(data);
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.confirm_required) {

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    addIntakePaymentPlan(btn, id, true);
                });

                $("#scheduleConfirmModalCancel").off("click");
                $("#scheduleConfirmModalCancel").on("click", function () {
                    addIntakePaymentPlan(btn, id, false);
                });
                confirmModal.show();
                return;
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const removeIntakePaymentPlan = (id) => {
    toggleLoader(true);
    const data = {
        plan: id
    }
    fetch('api/removeIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeIntakePaymentPlanConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const editIntakePaymentPlan = (btn, id, confirmation = null) => {
    console.log(id);
    const data = {
        id: id,
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
        confirmation: confirmation
    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/editIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.confirm_required) {

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    editIntakePaymentPlan(btn, id, true);
                });

                $("#scheduleConfirmModalCancel").off("click");
                $("#scheduleConfirmModalCancel").on("click", function () {
                    editIntakePaymentPlan(btn, id, false);
                });
                confirmModal.show();
                return;
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const markUserAttendnace = (btn) => {
    toggleBtnLoader(btn, true);

    fetch('api/markUserAttendnace', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: document.getElementById('mark-user-id').value,
            attendance_status: document.getElementById('mark-attendance-status').value,
            check_in: document.getElementById('mark-check-in').value,
            check_out: document.getElementById('mark-check-out').value,
            note: document.getElementById('mark-note').value,
            attendance_date: document.getElementById('mark-date').value
        })
    })
        .then(r => r.json())
        .then(d => {
            toggleBtnLoader(btn, false);
            console.log(d);

            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('markAttModal')).hide();
                showToast('success', 'Attendance saved successfully');
                setTimeout(() => location.reload(), 3000);
            } else {
                showToast('error', d.error || 'Failed to save attendance. Please check your input and try again.');
            }
        })
        .catch((error) => {
            console.log(error);
            toggleBtnLoader(btn, false);
            showToast('error', 'An error occurred. Please try again later.');
        });
}

const bulkMarkStatus = (statusId) => {
    toggleBtnLoader()
    const ids = [...document.querySelectorAll('.att-row-check:checked')].map(cb => cb.dataset.uid);
    if (!ids.length) return;
    if (!confirm(`Mark ${ids.length} employee(s) as status ${statusId}?`)) return;
    fetch('./api/attendance/bulk-save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_ids: ids,
            status_id: statusId,
            date: document.getElementById('attendance-date').value
        })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification('Bulk attendance saved', 'success');
                setTimeout(() => location.reload(), 600);
            } else showNotification(d.message || 'Error', 'error');
        });
}
const filterIntakePaymentPlanStudents = (id) => {
    const data = {
        id: id,
        plan: $("#payment-plan").val(),
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterIntakePaymentPlanStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#student-list-container").html(data);
                $("#student-list-container").hasClass('d-none') ? $("#student-list-container").removeClass('d-none') : "";

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const addStudentToIntakePaymentPlan = (btn, sid, intakeId) => {
    toggleBtnLoader(btn, true);
    selectedUsers = [];
    unSelectedUsers = [];
    selectedUsers.push(sid);
    const data = {
        intakeId: intakeId,
        paymentPlanId: selectedIntakePlanId,
        checkedStudents: selectedUsers,
        uncheckedStudents: unSelectedUsers,
        checkAll: false
    }

    const validationMap = {
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        paymentCategory: ["#payment-category", "#payment-category-feedback"],
        paymentPlan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addStudentsToPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

function generateAttendanceReport(btn) {
    const fromDate = document.getElementById('attReportFromDate').value;
    const toDate = document.getElementById('attReportToDate').value;

    /* Validation */
    clearAttFeedback();
    let valid = true;

    if (!fromDate) {
        document.getElementById('attFromDateFeedback').textContent = 'Please select a from date';
        valid = false;
    }
    if (!toDate) {
        document.getElementById('attToDateFeedback').textContent = 'Please select a to date';
        valid = false;
    }
    if (!valid) return;

    if (fromDate > toDate) {
        document.getElementById('attToDateFeedback').textContent =
            'To date must be on or after the from date';
        return;
    }

    /* Sanity: max 366 days */
    const days = Math.round((new Date(toDate) - new Date(fromDate)) / 86400000) + 1;
    if (days > 366) {
        document.getElementById('attToDateFeedback').textContent =
            'Maximum range is 366 days. Please narrow your selection.';
        return;
    }

    toggleBtnLoader(btn, true);

    fetch(
        `api/generateUserAttendanceReport.php` +
        `?from_date=${encodeURIComponent(fromDate)}` +
        `&to_date=${encodeURIComponent(toDate)}`
    )
        .then(async (response) => {
            const ct = response.headers.get('content-type') || '';

            if (ct.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }
            if (ct.includes('application/json')) {
                return response.json();
            }

            const raw = await response.text();
            console.error('Unexpected response:', raw);
            throw new Error('Unexpected server response');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);

            if (data instanceof Blob) {
                /* Trigger download */
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Attendance_Report_(${fromDate}_to_${toDate}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                /* Close modal & toast */
                bootstrap.Modal.getInstance(
                    document.getElementById('attendanceReportModal')
                )?.hide();
                showToast('success', 'Attendance report downloaded successfully');

            } else {
                showToast('error', data.error || 'Failed to generate report');
            }
        })
        .catch((err) => {
            toggleBtnLoader(btn, false);
            console.error(err);
            showToast('error', err.message || 'Something went wrong');
        });
}

const filterEnrollment = () => {
    const data = {
        text: $("#search-text").val(),
        agent: $("#agent-selector").val(),
        sort: $("#status-selector").val(),
        source: $("#source-selector").val(),
        regStatus: $("#reg-status-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterEnrollment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#leads-table-container").html(data);
                initializeLeads();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const addNewPaymentPlan = (btn, confirmation = null) => {
    const data = {
        faculty: $("#faculty-selector").val(),
        programmeId: $("#programme-selector").val(),
        intakeId: $("#intake-selector").val(),
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
        confirmation: confirmation
    }

    const validationMap = {
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addNewPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.confirm_required) {

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    addIntakePaymentPlan(btn, id, true);
                });

                $("#scheduleConfirmModalCancel").off("click");
                $("#scheduleConfirmModalCancel").on("click", function () {
                    addIntakePaymentPlan(btn, id, false);
                });
                confirmModal.show();
                return;
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const filterPaymentPlans = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        faculty: $("#faculty-selector").val(),
        programmeId: $("#programme-selector").val(),
        intakeId: $("#intake-selector").val(),
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    }

    fetch('api/filterPaymentPlans.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#accordionParent").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
}

const filterLecturers = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        role: $("#role-selector").val() || "",
        sort: $("#sort-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        update: $("#update-selector").val() || "",
        page: selectedPage || 1,
        selectUsers: selectedUsers || [],
        unSelectUsers: unSelectedUsers || [],
    };

    fetch('api/filterLecturers.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const removeLecture = (id) => {
    toggleLoader(true);
    const data = {
        id: id,
    }
    fetch('api/removeLecture.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.done) {
                $("#removeLectureConfirmModal").modal('hide');
                $("#lectureDetailsModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload() }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadPaymentTypes = () => {
    const plan = $("#payment-plan-selector").val();
    fetch(`api/loadPaymentType.php?id=${plan}`)
        .then((res) => res.json())
        .then((response) => {
            if (response.error) {
                showToast("error", response.error);
                $("#payment-type-selector").prop("disabled", true);
                return;
            }

            if (response.plan) {
                $("#payment-plan-selector").addClass("is-invalid");
                $("#payment-plan-feedback").text(response.plan);
                $("#payment-type-selector").prop("disabled", true).html("<option value='0'>- Select -</option>");
                return;
            }

            if (response.types.length != 0) {
                // Clear validation
                $("#payment-plan-selector").removeClass("is-invalid");
                $("#payment-plan-selector-feedback").text("");

                // Populate payment types
                let options = `<option value="0">- Select -</option>`;
                response.types.forEach(type => {
                    options += `<option value="${type.payment_type_id}">${type.payment_type}</option>`;
                });

                $("#payment-type-selector")
                    .html(options)
                    .prop("disabled", false);
                $("#fees-type").hasClass('d-none') ? $("#fees-type").removeClass('d-none') : "";
            } else {
                $("#plan-feedback").text("No payment type found for this plan.");
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const sendStudentTimeTable = (id) => {
    toggleLoader(true);
    fetch('api/sendStudentTimeTable.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ studentId: id })
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleLoader(false);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const markAsPaidLecturerPayments = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        userId: id,
        paidAmount: collectedAmount,
        lecturePayments: selectedPayments,
        assignmentPayments: selectedAssignmentPayments,
        paidDate: $("#date_paid").val(),
        note: $("#note").val()
    }

    const validationMap = {
        amount: ['amount', 'amount-feedback'],
        duration: ['duration', 'duration-feedback']
    }
    fetch('api/markAsPaidLecturerPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#markAsPaidModal").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000)
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again later.");
        });
}

function confirmCancelSchedule(btn, lectureId) {

    toggleBtnLoader(btn, true);

    fetch('api/cancelLecturerLecturePayment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lectureId: lectureId })
    })
        .then(res => res.json())
        .then(data => {
            toggleBtnLoader(btn, false);

            if (data.done) {
                bootstrap.Modal.getInstance(document.getElementById('cancelScheduleModal')).hide();
                showToast('success', data.done);
                setTimeout(() => { window.location.reload(); }, 3000)
            } else {
                showToast("error", "An error occured. Please try again later.");
            }
        })
        .catch(() => {
            showToast("error", "An error occured. Please try again later.");
        })
        .finally(() => {
            cancelScheduleLectureId = null;
        });
}

const rejectScheduledLecturePayment = (id) => {
    toggleLoader(true);
    const data = {
        paymentId: id,
    }

    fetch('api/rejectScheduledLecturePayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);

            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000)
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again later.");
        });
}

const addPortalSubscription = () => {
    toggleLoader(true);
    const data = {
        studentCount: $("#student-count").val(),
        amount: $("#amount").val(),
        note: $("#note").val()
    }
    console.log(data);
    fetch('api/addPortalSubscription.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#addSubscriptions").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('student')) {
                    $("#student-count").addClass("is-invalid");
                    $("#sc-feedback").text(response.student);
                } else {
                    $("#student-count").removeClass("is-invalid");
                    $("#sc-feedback").text("");
                }

                if (response.hasOwnProperty('amount')) {
                    $("#amount").addClass("is-invalid");
                    $("#am-feedback").text(response.amount);
                } else {
                    $("#amount").removeClass("is-invalid");
                    $("#am-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

function saveProgrammeDocuments(btn) {
    const programmeId = document.getElementById("programme-documents-programme-id").value;
    const feedback = document.getElementById("programme-documents-feedback");
    feedback.textContent = "";

    const selected = [];
    document.querySelectorAll(".programme-doc-checkbox:checked").forEach((cb) => selected.push(cb.value));

    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("programme_id", programmeId);
    form.append("document_types", JSON.stringify(selected));

    fetch("api/programmeDocuments.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                bootstrap.Modal.getOrCreateInstance(document.getElementById("programmeDocumentsModal")).hide();
            } else if (response.error) {
                feedback.textContent = response.error;
                showToast("error", response.error);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}