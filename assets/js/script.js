const BASE_STUDENT_PORTAL_URL = "https://portal.orinstolms.com";
const SORT_NONE = 0;
const SORT_ASC = 1;
const SORT_DESC = 2;

let selectedPage = 1;
let selectedPage2 = 1;
let sortDirection = 0;
let sortCondition1 = 0;
let sortCondition2 = 0;
let sortCondition3 = 0;
let sortCondition4 = 0;
let selectedUsers = [];
let unSelectedUsers = [];

$(window).on('load', function () {
    document.body.style.opacity = '1';

    // initializeTheme();
    initializeTheme2();
    initializeLanguage2();
    renderSearchHistory();
    toggleSkeleton();

    const toggleBtn = $('#toggleMenu');
    const sideMenu = $('#sideMenu');
    const appWrapper = $('#app-wrapper');

    const dropdown = document.querySelector('.header-ud-toggle-area');
    const dropdownMenu = new bootstrap.Dropdown(dropdown);
    let timeout;

    dropdown.parentElement.addEventListener('mouseenter', function () {
        clearTimeout(timeout);
        dropdownMenu.show();
    });

    dropdown.parentElement.addEventListener('mouseleave', function () {
        timeout = setTimeout(() => dropdownMenu.hide(), 300);
    });

    // Check if elements exist
    if (toggleBtn.length && sideMenu.length && appWrapper.length) {
        // Initialize menu state from localStorage
        const menuVisible = localStorage.getItem('menuVisible') !== 'false';
        setMenuState(menuVisible);

        // Toggle menu on click
        toggleBtn.on('click', function () {
            const isCurrentlyVisible = !sideMenu.hasClass('d-xl-block');
            setMenuState(!isCurrentlyVisible);
        });

        function setMenuState(visible) {
            //console.log(visible);
            if (visible) {
                // Show menu
                sideMenu.removeClass('d-xl-block');
                if (appWrapper.hasClass("app-wrapper") == true) {
                    appWrapper.removeClass("app-wrapper");
                    appWrapper.addClass("app-wrapper-full");
                }
            } else {
                // Hide menu
                sideMenu.addClass('d-xl-block');
                if (appWrapper.hasClass("app-wrapper") == false) {
                    appWrapper.removeClass("app-wrapper-full");
                    appWrapper.addClass("app-wrapper");
                }
            }
            localStorage.setItem('menuVisible', visible);
        }
    } else {
        console.error('Required elements not found');
    }

});

const initializeTheme = () => {
    // Get theme elements
    const htmlElement = document.documentElement;
    const themeItems = document.querySelectorAll('.theme-item');
    const lightIcon = document.querySelector('.light-theme-icon');
    const darkIcon = document.querySelector('.dark-theme-icon');

    // Theme setting function
    const setTheme = (theme) => {
        // Update UI icons
        if (theme === 'light') {
            lightIcon?.classList.remove('d-none');
            darkIcon?.classList.add('d-none');
        } else {
            lightIcon?.classList.add('d-none');
            darkIcon?.classList.remove('d-none');
        }

        // Update attributes
        htmlElement.setAttribute('data-bs-theme', theme);
        htmlElement.setAttribute('data-custom-theme', theme);

        // Save to localStorage (persists across tabs and sessions)
        localStorage.setItem('theme', theme);

        // Update dropdown UI
        themeItems.forEach(item => {
            const checkIcon = item.querySelector('.theme-check');
            const isActive = item.getAttribute('data-theme') === theme;
            item.classList.toggle('active-theme', isActive);
            checkIcon.style.display = isActive ? 'block' : 'none';
        });
    };

    const initThemeUI = () => {
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: light)').matches;
        const initialTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        setTheme(initialTheme);
        document.body.style.opacity = '1'; // Fade in content
    };

    themeItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            setTheme(e.currentTarget.getAttribute('data-theme'));
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === 'theme') {
            setTheme(event.newValue);
        }
    });

    initThemeUI();
};

const initializeTheme2 = () => {
    const htmlElement = document.documentElement;
    const themeButtons = document.querySelectorAll('.theme-toggle-btn');
    const lightBtn = document.getElementById('lightThemeBtn');
    const darkBtn = document.getElementById('darkThemeBtn');

    const setTheme = (theme) => {
        // Update HTML attributes
        htmlElement.setAttribute('data-bs-theme', theme);
        htmlElement.setAttribute('data-custom-theme', theme);

        // Save in local storage
        localStorage.setItem('theme', theme);

        // Update active button UI
        if (theme === 'light') {
            lightBtn.classList.add('btn-primary');
            darkBtn.classList.remove('btn-primary');
        } else {
            darkBtn.classList.add('btn-primary');
            lightBtn.classList.remove('btn-primary');
        }
    };

    const initThemeUI = () => {
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // If no stored theme → follow system
        const initialTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        setTheme(initialTheme);
    };

    // Listen for clicks
    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.getAttribute('data-theme');
            setTheme(theme);
        });
    });

    initThemeUI();
};

const initializeLanguage = () => {
    const htmlElement = document.documentElement;
    const langItems = document.querySelectorAll('.lang-item');
    const currentLangText = document.querySelector('.current-lang-text');

    async function loadTranslations(lang = "en") {
        try {
            const response = await fetch(`assets/i18n/${lang}.json`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const translations = await response.json();

            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");

                if (key.startsWith("[placeholder]")) {
                    const cleanKey = key.replace("[placeholder]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("placeholder", translations[cleanKey]);
                    }
                } else if (key.startsWith("[title]")) {
                    const cleanKey = key.replace("[title]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("title", translations[cleanKey]);
                    }
                } else if (translations[key]) {
                    el.textContent = translations[key];
                }
            });

            // Update attributes
            htmlElement.setAttribute('lang', lang);
            htmlElement.setAttribute('custom-lang', lang);

            // Save language
            localStorage.setItem("lang", lang);

            // Update dropdown checkmarks
            if (langItems.length == 0) return;
            langItems.forEach(item => {
                const checkIcon = item.querySelector('.lang-check');
                const isActive = item.dataset.lang === lang;
                item.classList.toggle('active-lang', isActive);
                if (checkIcon) checkIcon.style.display = isActive ? 'inline' : 'none';
                // Update dropdown button icon
                if (isActive) {
                    const fullText = item.textContent.trim();
                    if (lang == "en") {
                        currentLangText.textContent = "EN";
                    } else {
                        const shortText = fullText.substring(0, 3);
                        currentLangText.textContent = shortText;
                    }
                }
            });

        } catch (err) {
            console.error("Error loading translations:", err);
        }
    }

    const initLangUI = () => {
        const storedLang = localStorage.getItem("lang") || "en";
        loadTranslations(storedLang);
    };

    // Event listeners
    langItems.forEach(item => {
        item.addEventListener("click", (e) => {
            e.preventDefault();
            loadTranslations(item.dataset.lang);
        });
    });

    // Sync across tabs
    window.addEventListener('storage', (event) => {
        if (event.key === 'lang') {
            loadTranslations(event.newValue);
        }
    });

    initLangUI();
};

const initializeLanguage2 = () => {
    const htmlElement = document.documentElement;
    const dropdownButton = document.querySelector("#languageDropdown");
    const languageItems = document.querySelectorAll(".language-select");

    // Load JSON translations
    async function loadTranslations(lang = "en") {
        const langKey = lang.toLowerCase(); // English → english.json

        try {
            const response = await fetch(`assets/i18n/${langKey}.json`);
            if (!response.ok) throw new Error("Translation file not found: " + langKey);

            const translations = await response.json();

            // Apply translations
            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");

                if (key.startsWith("[placeholder]")) {
                    const cleanKey = key.replace("[placeholder]", "");
                    if (translations[cleanKey]) el.setAttribute("placeholder", translations[cleanKey]);
                } else if (key.startsWith("[title]")) {
                    const cleanKey = key.replace("[title]", "");
                    if (translations[cleanKey]) el.setAttribute("title", translations[cleanKey]);
                } else if (translations[key]) {
                    el.textContent = translations[key];
                }
            });

            // Update html tag
            htmlElement.setAttribute("lang", langKey);
            htmlElement.setAttribute("custom-lang", langKey);

            // Save selected language
            localStorage.setItem("lang", lang);

            // Update button label
            dropdownButton.textContent = lang == "en" ? "English" : lang == "si" ? "සිංහල" : "தமிழ்";

        } catch (err) {
            console.error("Error:", err);
        }
    }

    // Initialize UI on page load
    const initLangUI = () => {
        const savedLang = localStorage.getItem("lang") || "en";
        loadTranslations(savedLang);
    };

    // Handle click events
    languageItems.forEach(item => {
        item.addEventListener("click", e => {
            e.preventDefault();
            const selectedLang = item.getAttribute("data-value");

            loadTranslations(selectedLang);
        });
    });

    // Sync across browser tabs
    window.addEventListener("storage", event => {
        if (event.key === "lang") {
            dropdownButton.textContent = event.newValue;
            loadTranslations(event.newValue);
        }
    });

    initLangUI();
};

const createLoader = () => {
    if ($(".loader-container").length) return; // already exists

    const loaderContainer = $(`
        <div class="loader-container" style="display:none;">
            <div class="fading-bars">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    `);

    $("body").append(loaderContainer);
};


const toggleLoader = (isLoading) => {
    createLoader();
    if (isLoading) {
        $(".loader-container").fadeIn();
    } else {
        $(".loader-container").fadeOut(1000);
        setTimeout(() => { $(".loader-container").remove(); }, 1000);

    }
};

function toggleBtnLoader(btn, state) {
    if (!btn.dataset.originalText) {
        btn.dataset.originalText = btn.innerHTML.trim();
    }

    if (state === true) {
        btn.disabled = true;
        btn.style.cursor = "not-allowed";

        btn.innerHTML = `
            <iconify-icon icon="eos-icons:bubble-loading" width="22" height="22" class="text-white"></iconify-icon>
        `;
        return;
    }
    if (state === "done") {
        btn.innerHTML = `
            <iconify-icon icon="line-md:check-all" width="22" height="22"></iconify-icon>
        `;

        return;
    }
    if (state === false) {
        btn.disabled = false;
        btn.style.cursor = "pointer";
        btn.innerHTML = btn.dataset.originalText;
    }
}
const toggleSkeleton = () => {
    const delay = ms => new Promise(res => setTimeout(res, ms));
    (async () => {

        document.getElementById('skeleton-list').classList.add('d-none');
        document.getElementById('real-list').classList.remove('d-none');
    })();
}

const notifications = document.querySelector(".notifications");

const toastDetails = {
    timer: 3000,
    success: {
        topic: 'Success',
        icon: 'mdi:check-circle',  // Iconify icon for success
        defaultText: 'This is a success toast.',
    },
    error: {
        topic: 'Error',
        icon: 'mdi:close-circle',  // Iconify icon for error
        defaultText: 'This is an error toast.',
    },
    warning: {
        topic: 'Warning',
        icon: 'mdi:alert-circle',  // Iconify icon for warning
        defaultText: 'This is a warning toast.',
    },
    info: {
        topic: 'Info',
        icon: 'mdi:information',  // Iconify icon for info
        defaultText: 'This is an information toast.',
    }
}

const removeToast = (toast) => {
    toast.classList.add("hide");
    if (toast.timeoutId) clearTimeout(toast.timeoutId); // Clear the timeout for the toast
    setTimeout(() => toast.remove(), 500); // Remove the toast after 500ms
}

const showToast = (type, message) => {
    const { topic, icon, defaultText } = toastDetails[type];
    const toast = document.createElement("li");
    toast.className = `custom-toast ${type}`;
    const toastMessage = message || defaultText;

    // Using Iconify icons in place of FontAwesome
    toast.innerHTML = `<div class="column">
                         <iconify-icon icon="${icon}" width="24" height="24"></iconify-icon>
                         <div class="custom-toast-text">
                           <span class="custom-toast-type">${topic}</span>
                           <span class="custom-toast-message">${toastMessage}</span>
                         </div>
                       </div>
                       <iconify-icon icon="mdi:close" width="24" height="24" class="close-btn" onclick="removeToast(this.parentElement)"></iconify-icon>`;

    notifications.appendChild(toast);

    toast.timeoutId = setTimeout(() => removeToast(toast), toastDetails.timer);
};

const togglePasswordVisibility = () => {
    const passwordInput = $('#password');
    const togglePasswordIcon = $('#togglePassword');

    const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
    passwordInput.attr('type', type);

    // Change the icon based on the input type
    if (type === 'password') {
        togglePasswordIcon.attr('icon', 'tabler:eye-off');   // Show eye-off icon
    } else {
        togglePasswordIcon.attr('icon', 'eva:eye-outline');  // Show eye icon
    }
}


const initializeMultipleSelect = (condition) => {
    const selectValue = $('#' + condition + '-selector').val();
    const multipleSelect = $('#' + condition + '-multi-selector').get(0);
    //console.log(multipleSelect.options.length);
    for (let i = 0; i < multipleSelect.options.length; i++) {
        //console.log(multipleSelect.options[i].value);
        if (multipleSelect.options[i].value === selectValue) {
            multipleSelect.options[i].selected = true;
            break;
        }
    }

    // Optionally, update the display container here with the selected value
    updateSelectedItemsDisplay(multipleSelect, condition);
}

const updateSelectedItemsDisplay = (multipleSelect, condition) => {
    const selectedItemsContainer = $('#selected-' + condition + '-container');
    const child = $('#child');
    selectedItemsContainer.empty(); // Clear the container

    const length = multipleSelect.selectedOptions.length;
    if (length >= 1) {
        selectedItemsContainer.removeClass('d-none');
        selectedItemsContainer.addClass('bl-solid');
    } else {
        selectedItemsContainer.removeClass('bl-solid');
    }
    // Loop through selected options and display them
    for (let i = 0; i < length; i++) {
        const option = multipleSelect.selectedOptions[i];
        // Clone the child element
        const clonedChild = child.clone();

        // Update the text of the cloned element
        clonedChild.find('span').first().text(option.text);

        // Update the onclick function with the appropriate value
        clonedChild.find('iconify-icon').on('click', () => {
            removeItem(option.value, condition);
        });

        // Append the cloned child to the selected items container
        selectedItemsContainer.append(clonedChild);
    }
}

const removeItem = (value, condition) => {
    const multipleSelect = $('#' + condition + '-multi-selector').get(0);
    let relationalSelect;

    // Deselect the option in the multiple select
    for (let i = 0; i < multipleSelect.options.length; i++) {
        if (multipleSelect.options[i].value === value) {
            multipleSelect.options[i].selected = false;
            break;
        }
    }

    // Update the selected items display after removal
    updateSelectedItemsDisplay(multipleSelect, condition);
    if (condition === 'faculty') {
        if ($("#programme-multi-selector").val().length > 0) {
            removeDeletedFacultyProgrammes(value);
        }
    } else if (condition === 'programme') {
        if ($("#intake-multi-selector").val().length > 0) {
            removeDeletedProgrammeIntakes(value);
        }
    }

    $('#' + condition + '-selector').val(0);

}

const uploadImage = (input, displayTag) => {
    const fileInput = document.getElementById(input);
    const imageTag = document.getElementById(displayTag);
    const closeModalButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    const image = document.getElementById("image");
    const cropButton = document.getElementById("crop-button");
    let cropper;

    // Trigger file input click
    fileInput.click();

    // Reset cropper and image before selecting a new file
    fileInput.addEventListener('click', () => {
        if (cropper) {
            cropper.destroy(); // Destroy previous cropper instance
        }
        image.src = ''; // Clear previous image
    }, { once: true });

    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0];

        if (selectedFile) {
            const img = new Image();
            const reader = new FileReader();

            reader.onload = function (e) {
                img.src = e.target.result;

                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Set a consistent minimum size for the image
                    const minSize = 500; // Minimum width/height
                    const scale = Math.max(minSize / img.width, minSize / img.height);

                    canvas.width = img.width * scale;
                    canvas.height = img.height * scale;

                    // Draw the resized image on the canvas
                    ctx.fillStyle = '#fff'; // Optional: Add a background color
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    // Convert the canvas to a Blob and set it to the image source
                    canvas.toBlob(function (blob) {
                        const resizedURL = URL.createObjectURL(blob);
                        image.src = resizedURL;

                        // Show the modal
                        $('#crop-modal').modal('show');

                        // Initialize Cropper.js
                        if (cropper) {
                            cropper.destroy();
                        }

                        cropper = new Cropper(image, {
                            aspectRatio: 1, // Maintain square crop
                            viewMode: 1,    // Fully show the image in the canvas
                            autoCropArea: 1, // Maximize crop area
                            responsive: true, // Resize dynamically
                            scalable: true, // Allow scaling
                            minContainerWidth: 500, // Minimum cropper container width
                            minContainerHeight: 500, // Minimum cropper container height
                        });
                    }, 'image/png');
                };
            };

            reader.readAsDataURL(selectedFile);
        }
    }, { once: true });

    $('#crop-modal').on('show.bs.modal', function () {
        // Remove inert attribute and allow interactions
        $(this).removeAttr('aria-hidden').prop('inert', false);
    }).on('hidden.bs.modal', function () {
        // Add inert attribute and prevent interactions when hidden
        $(this).attr('aria-hidden', 'true').prop('inert', true);
    });

    // Add crop logic with proper checks
    cropButton.addEventListener("click", function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas();

            if (canvas) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        const reader = new FileReader();
                        reader.onloadend = function () {
                            const file = new File([blob], "cropped_image.png", {
                                type: "image/png",
                            });

                            // Assign cropped image to file input and display
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            fileInput.files = dataTransfer.files;

                            imageTag.src = reader.result; // Update preview image
                        };

                        reader.readAsDataURL(blob);
                        $('#crop-modal').modal('hide');
                    }
                }, "image/png");
            }
        }
    });


    // Handle modal close and reset
    closeModalButtons.forEach((button) => {
        button.addEventListener("click", function () {
            fileInput.value = ""; // Reset file input
            image.src = "";       // Reset image source
            $('#crop-modal').modal('hide');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });
    });
};

const uploadCover = (input, displayTag) => {
    const fileInput = document.getElementById(input);
    const imageTag = document.getElementById(displayTag);
    const closeModalButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    const image = document.getElementById("image");
    const cropButton = document.getElementById("crop-button");
    let cropper;

    // Trigger the file input click
    fileInput.click();

    // Reset cropper and image before selecting a new file
    fileInput.addEventListener('click', () => {
        if (cropper) {
            cropper.destroy(); // Destroy previous cropper instance
        }
        image.src = '';  // Clear previous image
    }, { once: true });

    // Handle file selection
    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0];

        if (selectedFile) {
            const reader = new FileReader();

            reader.onload = function (e) {
                image.src = e.target.result;
                $('#crop-modal').modal('show');
                image.onload = function () {  // Ensure image is fully loaded
                    if (cropper) {
                        cropper.destroy(); // Destroy previous cropper
                    }
                    cropper = new Cropper(image, {
                        aspectRatio: 16 / 9, // Maintain square crop
                        viewMode: 1,    // Fully show the image in the canvas
                        autoCropArea: 1, // Maximize crop area
                        responsive: true, // Resize dynamically
                        scalable: true, // Allow scaling
                        minContainerWidth: 500, // Minimum cropper container width
                        minContainerHeight: 500, // Minimum cropper container height
                    });
                };
            };

            reader.readAsDataURL(selectedFile);
        }
    }, { once: true });

    cropButton.addEventListener("click", function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas();

            if (canvas) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        const reader = new FileReader();
                        reader.onloadend = function () {
                            const file = new File([blob], "cropped_image.png", {
                                type: "image/png",
                            });

                            // Assign cropped image to file input and display
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            fileInput.files = dataTransfer.files;

                            imageTag.src = reader.result; // Update preview image
                        };

                        reader.readAsDataURL(blob);
                        $('#crop-modal').modal('hide');
                    }
                }, "image/png");
            }
        }
    });

    // Reset modal and cropper on close
    closeModalButtons.forEach((button) => {
        button.addEventListener("click", function () {
            fileInput.value = '';
            image.src = "";
            $('#crop-modal').modal('hide');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });
    });
};

const uploadRecording = () => {

    // Get the video input and preview elements
    const videoInput = document.getElementById('video-input');
    const videoPreview = document.getElementById('video-preview');


    videoInput.click();
    // Handle file selection
    videoInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0]; // Get the selected video file

        if (selectedFile) {
            const fileURL = URL.createObjectURL(selectedFile); // Create a URL for the file
            videoPreview.src = fileURL; // Set the video src to the file URL
            videoPreview.style.display = 'block'; // Display the video preview
            videoPreview.load(); // Load the video to prepare for playback
            $("#empty-rec").addClass("d-none");
            $("#video-preview").removeClass("d-none");
        }
    });
}

const uploadFile = () => {
    const fileInput = document.getElementById('file-input2');
    const fileNameDisplay = document.getElementById('file-name');

    fileInput.click();
    // Handle file selection
    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0]; // Get the selected file

        if (selectedFile) {
            // Display file info
            fileNameDisplay.textContent = selectedFile.name; // Show the file name

        }
    });
}

const getTableWidth = (table, pagination) => {
    //console.log(table);
    // Get the table element
    var table = document.querySelector(table);

    // Get the table's width
    var tableWidth = table.offsetWidth;

    // Output the width to the console or use as needed
    document.querySelector(pagination).style.width = tableWidth + 'px';
    //console.log(tableWidth);
}
var about;
var bio;
var achivements;
var experience;
const initializeAddUser = () => {
    about = new RichTextEditor("#div_editor1");
    bio = new RichTextEditor("#div_editor2");
    achivements = new RichTextEditor("#div_editor3");
    experience = new RichTextEditor("#div_editor4");

    var today = new Date();
    var minDate = new Date(today.getFullYear() - 70, today.getMonth(), today.getDate());
    var maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    var minDateString = minDate.toISOString().split('T')[0];
    var maxDateString = maxDate.toISOString().split('T')[0];

    // Set the min and max attributes on the input field
    document.getElementById('user-dob').setAttribute('min', minDateString);
    document.getElementById('user-dob').setAttribute('max', maxDateString);

    const StudentNICConfig = {
        title: 'What is NIC Number?',
        subtitle: 'The National Identity Card (NIC) number used to identify a student.',
        sections: [{
            title: 'Valid Sri Lankan NIC Formats',
            items: [
                {
                    bullet: '•',
                    text: 'New NIC Format: 200557903331'
                },
                {
                    bullet: '•',
                    text: 'Old NIC Format: 825241280V'
                },
                {
                    bullet: '•',
                    text: 'The system validates both old and new Sri Lankan NIC formats.',
                    highlight: true
                }
            ]
        }],
        warning: 'NIC must follow a valid Sri Lankan format. Accepted formats: 12 digits (example: 200557903331) or 9 digits followed by V/X (example: 825241280V). Invalid NIC numbers will be rejected.'
    };

    InfoTooltip.create('nic-prefix-info', StudentNICConfig);
    $('#nic-info-wrapper').append(InfoTooltip.getElement('nic-prefix-info'));
}

const checkDigit = (event) => {
    const charCode = event.which ? event.which : event.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        event.preventDefault();
    }
}

const checkValidChar = (event) => {
    var charCode = event.which ? event.which : event.keyCode;
    if (!(charCode >= 48 && charCode <= 57) && charCode !== 86 && charCode !== 118) {
        event.preventDefault(); // Prevent any other characters
    }
}

const initializeViewUser = () => {
    about = new RichTextEditor("#div_editor1");
    bio = new RichTextEditor("#div_editor2");
    achivements = new RichTextEditor("#div_editor3");
    experience = new RichTextEditor("#div_editor4");

    var today = new Date();
    var minDate = new Date(today.getFullYear() - 70, today.getMonth(), today.getDate());
    var maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    var minDateString = minDate.toISOString().split('T')[0];
    var maxDateString = maxDate.toISOString().split('T')[0];

    // Set the min and max attributes on the input field
    document.getElementById('user-dob').setAttribute('min', minDateString);
    document.getElementById('user-dob').setAttribute('max', maxDateString);

    const StudentNICConfig = {
        title: 'What is NIC Number?',
        subtitle: 'The National Identity Card (NIC) number used to identify a student.',
        sections: [{
            title: 'Valid Sri Lankan NIC Formats',
            items: [
                {
                    bullet: '•',
                    text: 'New NIC Format: 200557903331'
                },
                {
                    bullet: '•',
                    text: 'Old NIC Format: 825241280V'
                },
                {
                    bullet: '•',
                    text: 'The system validates both old and new Sri Lankan NIC formats.',
                    highlight: true
                }
            ]
        }],
        warning: 'NIC must follow a valid Sri Lankan format. Accepted formats: 12 digits (example: 200557903331) or 9 digits followed by V/X (example: 825241280V). Invalid NIC numbers will be rejected.'
    };

    InfoTooltip.create('nic-prefix-info', StudentNICConfig);
    $('#nic-info-wrapper').append(InfoTooltip.getElement('nic-prefix-info'));

}

const viewFile = (fileName) => {
    //console.log(fileName);
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/cv/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${fileName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/cv/${fileName}`;
        }
    }
}

const tableCheckEvents = () => {
    var isChecked = $("#check-all").prop('checked');
    $('.row-check').prop('checked', isChecked);
    if (isChecked) {
        selectedUsers = [];
        unSelectedUsers = [];
    } else {
        if (selectedUsers.length == 0 || unSelectedUsers.length == 0) {
            selectedUsers = [];
            unSelectedUsers = [];
        }
    }

}

const checkRowEvents = (id) => {
    var isChecked = $("#check-user" + id).prop('checked');

    //console.log(isChecked)

    if (isChecked) {
        if (!selectedUsers.includes(id)) {
            if (unSelectedUsers.length != 0 && unSelectedUsers.includes(id)) {
                unSelectedUsers = unSelectedUsers.filter(userId => userId !== id);
            } else {
                selectedUsers.push(id);
            }
        }
        $("#check-all").prop('checked', false);
    } else {
        $("#check-all").prop('checked', false);
        if (selectedUsers.length != 0 && selectedUsers.includes(id)) {
            selectedUsers = selectedUsers.filter(userId => userId !== id);
        } else {
            unSelectedUsers.push(id);
        }
    }
};

const setDefault = () => {
    $("#check-all").prop('checked', false);
    $("#update-selector").val(0);
    selectedUsers = [];
    unSelectedUsers = [];
}

function toggleSort(current) {
    return current === SORT_ASC ? SORT_DESC : SORT_ASC;
}

const handleUsersPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterUsers();
}

const selectFilterCondition = () => {
    selectedPage = 1;
    filterUsers();
}

const loadFacultyEditModel = (id, name, image) => {
    $("#edit-faculty-name").val(name);
    $("#edit-faculty-cover").attr('src', `./assets/imgs/faculties-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editFaculty(this, id);
    });
    $("#editFaculty").modal('show');
}

const loadProgrammeEditModel = (id, name, image, duration) => {
    $("#edit-programme-name").val(name);
    $("#edit-programme-cover").attr('src', `./assets/imgs/programmes-covers/${image}`);
    $("#edit-programme-duration").val(duration);
    $("#save-edit").off('click').on('click', function () {
        editProgramme(this, id);
    });
    $("#editProgramme").modal('show');
}
const loadIntakeEditModel = (id, name, image, start, end, letter, number) => {
    $("#edit-intake-name").val(name);
    $("#ei-start-date").val(start);
    $("#ei-end-date").val(end);
    $("#ei-letter").val(letter);
    $("#ei-sequence-number").val(number);
    $("#edit-intake-cover").attr('src', `./assets/imgs/intakes-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editIntake(this, id);
    });
    $("#editIntake").modal('show');
}


const loadLevelEditModel = (id, name, image) => {
    $("#edit-level-name").val(name);
    $("#edit-level-cover").attr('src', `./assets/imgs/levels-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editLevel(this, id);
    });
    $("#editLevel").modal('show');
}

const loadSubjectEditModel = (id, name, image, hours, type, group) => {

    $("#edit-subject-name").val(name);
    $("#edit-subject-cover").attr('src', `./assets/imgs/subjects-covers/${image}`);
    $("#edit-subject-hours").val(hours);
    $("#edit-subject-type").val(type);
    if (type == 2) {
        $("#edit-subject-group-display-area").removeClass('d-none');
        $("#edit-subject-group").val(group);
    }
    $("#save-edit").off('click').on('click', function () {
        editSubject(this, id);
    });
    $("#editSubject").modal('show');
}


const downloadCSV = () => {
    const filePath = 'assets/uploads/csv-format/studentBulkUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'studentData.csv';
    link.click();
}

const handleStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudents();
}

const handleIntakeHasStudentPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterIntakeHasStudents(id);
}

const updateUserStatus = (update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedUserUpdate(update);
}
const updateStudentStatus = (update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedStudentUpdate(update);
}


const updateSubjectStudentStatus = (subject, update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedSubjectStudentsUpdate(subject, update);
}

const acceptFutureDate = (id) => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById(id).setAttribute("min", today);
}

const initializeSubjectHasAssignments = () => {


}

const initializeScheduleLecture = () => {
    $("#physical-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#online-check-box").prop("checked", false);
        } else {
            $("#online-check-box").prop("checked", true);
        }
        if ($("#lecture-link-area").hasClass("d-none") == false) {
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#online-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#physical-check-box").prop("checked", false);
            $("#lecture-link-area").removeClass("d-none");
        } else {
            $("#physical-check-box").prop("checked", true);
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#oneTimeLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#recurringLecture").prop("checked", false);
            $("#onetimeLectureArea").removeClass("d-none");
        } else {
            $("#recurringLecture").prop("checked", true);
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#recurringLectureArea").hasClass("d-none") == false) {
            $("#recurringLectureArea").addClass("d-none");
        }

        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }

    });

    $("#recurringLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#oneTimeLecture").prop("checked", false);
            $("#recurringLectureArea").removeClass("d-none");
        } else {
            $("#oneTimeLecture").prop("checked", true);
            $("#recurringLectureArea").addClass("d-none");
        }
        if ($("#onetimeLectureArea").hasClass("d-none") == false) {
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }
    });

}

const handleClassSchedulePagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterScheduledLectures();
}

const viewLectureAttachment = (fileName, tuteName) => {
    //console.log(fileName);
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/lectures-attachments/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${tuteName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/lectures-attachments/${tuteName}`;
        }
    }
}
var studentPolicy;
const initializeStudentPolicy = () => {
    studentPolicy = new RichTextEditor("#div_editor1");
}
var newsContent;
const initializeNewsContent = () => {
    newsContent = new RichTextEditor("#div_editor1");
}

const initializeUploadRecording = () => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("expire-date").setAttribute("min", today);
}

const displaySubjectGroup = (id) => {
    if (id == 2) {
        $("#subject-group-display-area").removeClass("d-none");
        $("#subject-group-add-area").removeClass("d-none");
    } else {
        $("#subject-group-display-area").addClass("d-none");
        $("#subject-group-add-area").addClass("d-none");
    }
}

const displayAddNewSubjectGroupInput = () => {
    $("#subject-group").val(0);
    $("#subject-group-display-area").addClass("d-none");
    $("#subject-group-add-area").addClass("d-none");
    $("#add-subject-group-input-area").removeClass("d-none");
}

const viewAssignmentsAttachement = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/assignments-attachments/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${fileName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/assignments-attachments/${fileName}`;
        }
    }
}

const loadAssignmentEditModel = (data) => {
    //console.log(data);
    $("#em-code").text(data.assignment_code);
    $("#em-topic").text(data.assignment);

    const start = new Date(data.start_date);
    const end = new Date(data.end_date);
    const diffInMs = end - start;
    const days = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    const weeks = Math.floor(days / 7);

    $("#em-duration").text(Number.isInteger(weeks) && weeks >= 0 == true ? weeks + " Weeks" : days == 0 ? " - -" : days + " Days");

    $("#editAssignment").modal("show");
}


const handleSubjectHasAssignmentsPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByDate':
            selectedPage = 1;
            sortCondition1 = sortCondition1 == '1' ? '2' : '1';
            break;
    }

    filterSubjectHasAssignments(id);
}
const viewAssignment = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/assignments-attachments/${fileName}`, '_blank');
    }
}

const downloadMarksUploadCSV = (code) => {
    const filePath = './assets/uploads/csv-format/assignmentMarksUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'AssignmentMarksUpload-AssignmentCode-' + code + '.csv';
    link.click();
}

const downloadSelectedStudentAssignment = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`${BASE_STUDENT_PORTAL_URL}/assets/uploads/assignments/${fileName}`, '_blank');
    }
}

const loadAddMarkModel = (intakeId, code, topic, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#topic").text(topic);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark")
            .off("click")
            .on("click", function () {
                addAssignmentMark(this, id, intakeId);
            });
    } else {
        $("#saveMark")
            .off("click")
            .on("click", function () {
                addRecorrectionAssignmentMark(this, id, intakeId);
            });
    }

    $("#addMark").modal('show');
}

const loadEditMarkModel = (assignmentId, code, topic, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-topic").text(topic);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editAssignmentMark('" + id + "','" + assignmentId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editRecorrectionAssignmentMark('" + id + "','" + assignmentId + "')");
    }


    $("#editMark").modal('show');
}

const loadLecturerAddMarkModel = (intakeId, code, topic, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#topic").text(topic);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark").attr("onclick", "addLecturerAssignmentMark('" + id + "','" + intakeId + "')");
    } else {
        $("#saveMark").attr("onclick", "addLecturerRecorrectionAssignmentMark('" + id + "','" + intakeId + "')");
    }

    $("#addMark").modal('show');
}

const loadLecturerEditMarkModel = (intakeId, code, topic, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-topic").text(topic);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editLecturerAssignmentMark('" + id + "','" + intakeId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editLecturerRecorrectionAssignmentMark('" + id + "','" + intakeId + "')");
    }


    $("#editMark").modal('show');
}


const handleStudentsAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 == 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudentsAssignments(assignmentId);
}

const loadExaminationAdvancedDetails = () => {
    const type = $("#exam-type").val();

    if (type == 1) {
        $("#physical-examination").removeClass("d-none");
        if ($("#online-examination").hasClass("d-none") == false) {
            $("#online-examination").addClass("d-none");
        }
        if ($("#quiz-type-section").hasClass("d-none") == false) {
            $("#quiz-type-section").addClass("d-none");
        }
    } else if (type == 2) {
        $("#online-examination").removeClass("d-none");
        if ($("#physical-examination").hasClass("d-none") == false) {
            $("#physical-examination").addClass("d-none");
        }
        if ($("#quiz-type-section").hasClass("d-none") == false) {
            $("#quiz-type-section").addClass("d-none");
        }
    } else if (type == 3) {
        $("#quiz-type-section").removeClass("d-none");
        $("#online-examination").removeClass("d-none");
        if ($("#physical-examination").hasClass("d-none") == false) {
            $("#physical-examination").addClass("d-none");
        }
    }


}
const downloadOnlineExaminationCSVFormat = () => {
    const type = $("#exam-type").val();
    if (type == 2) {
        const filePath = 'assets/uploads/csv-format/essayQuestionsUpload.csv';
        const link = document.createElement('a');
        link.href = filePath;
        link.download = 'Essay Questions Upload.csv';
        link.click();
    } else {
        const filePath = 'assets/uploads/csv-format/quizUpload.csv';
        const link = document.createElement('a');
        link.href = filePath;
        link.download = 'Quiz Upload.csv';
        link.click();
    }

}

const handleSubjectHasExaminationsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            selectedPage = pageId;
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectHasExaminations(subjectId);
}


const userPermissions = {};
const chipBoxAction = (clickedElement) => {
    const $chip = $(clickedElement);
    const userId = $chip.data('user-id');
    const permissionId = $chip.data('value');

    $chip.toggleClass('selected');

    if (!userPermissions[userId]) {
        userPermissions[userId] = [];
    }

    if ($chip.hasClass('selected')) {
        if (!userPermissions[userId].includes(permissionId)) {
            userPermissions[userId].push(permissionId);
        }
    } else {
        userPermissions[userId] = userPermissions[userId].filter(perm => perm !== permissionId);
    }

};


const removePermissionToAddUser = (userId, deleteIcon) => {
    const userPermissionTemplate = $(deleteIcon).closest('#user-permission-template');

    if (userPermissionTemplate.length) {
        userPermissionTemplate.remove();
        addNewPermissionsUserCount--;

        if (userPermissions[userId]) {
            delete userPermissions[userId];
        }
        newPermissionUsers = newPermissionUsers.filter((id) => id !== userId);

        if (addNewPermissionsUserCount == 0) {
            $("#save-permission-btn-area").addClass("d-none");
            $('#empty-users-display-area').removeClass('d-none');
        }
    } else {
        showToast('error', 'Something went wrong. Please try again.');
    }
};

const handleFacultyPermissionPagination = (facultyId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterFacultyPermissions(facultyId);
}

let selectedPermissions = [];
let unSelectedPermissions = [];

$(document).on("change", ".row-check", function () {
    const permissionId = $(this).attr("id").replace("check-permission", "");
    if ($(this).is(":checked")) {
        if (!selectedPermissions.includes(permissionId)) {
            selectedPermissions.push(permissionId);
        }
        unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
    } else {
        selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
    }

});

$("#user-permission-update-selector").on("change", function () {
    const selectedValue = $(this).val();

    $(".row-check").each(function () {
        const permissionId = $(this).attr("id").replace("check-permission", "");
        const statusCell = $(this).closest("tr").find(".table-btn");
        const statusText = statusCell.text().trim();

        if (selectedValue === "1") {
            if (statusText === "Inactive") {
                $(this).prop("checked", true);
                if (!selectedPermissions.includes(permissionId)) {
                    selectedPermissions.push(permissionId);
                    unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
                }
            } else {
                $(this).prop("checked", false);
                if (!unSelectedPermissions.includes(permissionId)) {
                    selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
                }
            }
        } else if (selectedValue === "2") {
            if (statusText === "Active") {
                $(this).prop("checked", true);
                if (!selectedPermissions.includes(permissionId)) {
                    selectedPermissions.push(permissionId);
                    unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
                }
            } else {
                $(this).prop("checked", false);
                if (!unSelectedPermissions.includes(permissionId)) {
                    selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
                }
            }
        } else {
            $(this).prop("checked", false);
            selectedPermissions = [];
            unSelectedPermissions = [];
        }
    });

});

const togglePermissionCheckbox = (perId) => {
    const checkbox = $(`#check-permission${perId}`);
    const isChecked = checkbox.prop('checked');

    // Toggle the checkbox state
    checkbox.prop('checked', !isChecked).trigger('change');
}

const handleProgrammePermissionPagination = (programmeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterProgrammePermissions(programmeId);
}

const handleIntakePermissionPagination = (intakeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterIntakePermissions(intakeId);
}

const handleLevelPermissionPagination = (levelId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLevelPermissions(levelId);
}

const handleSubjectPermissionPagination = (subejctId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectPermissions(subejctId);
}

const initializeAddQuiz = () => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById('exam-date').setAttribute("min", today);

    const now = new Date();
    const currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    //console.log(currentTime);

    document.getElementById('start-time').setAttribute("min", currentTime);
    document.getElementById('end-time').setAttribute("min", currentTime);
}


let quizCount = 0;

$('#add-quiz-btn').click(function () {

    if (quizCount == 0) {
        quizCount = $('.quiz-item').length;
    }

    quizCount++; // Increment quiz count
    if ($("#quiz-questions-container").hasClass('d-none')) {
        $("#quiz-questions-container").removeClass('d-none');
        $("#empty-question-display-area").addClass('d-none');
    }
    const quizHTML = `
            <div class="col-12 p-0 mb-3 quiz-item" data-quiz-id="${quizCount}" data-quiz-status="2">
                <div class="row p-0 m-0">
                    <div class="col-12 p-2 px-md-4 pe-md-4 pt-md-2 pb-md-2 d-flex p-0 m-0 align-items-center bl-solid" data-bs-toggle="collapse" data-bs-target="#quiz-body-${quizCount}" aria-expanded="false" aria-controls="quiz-body-${quizCount}">
                        <span class="text-16 main-text flex-grow-1">Quiz ${quizCount}</span>
                        <div class="col-6 d-flex justify-content-end">
                            <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text delete-quiz-btn"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-12 p-0 m-0 bl-solid-lrb p-4 accordion-collapse collapse" id="quiz-body-${quizCount}" data-bs-parent="#quiz-container">
                        <div class="row gy-3 accordion-body">
                            <div class="col-12 d-flex flex-column">
                                <label for="" class="text-16 sub-text">Question</label>
                                <div class="d-flex align-items-center gx-3 mt-3">
                                    <div class="d-flex align-items-center flex-grow-1 pe-3">
                                        <input type="text" class="form-control custom-input question-input" placeholder="Enter question">
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <img src="./assets/imgs/common/vector.png" alt="add image question">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 1">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 1">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 2">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 2">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 3">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 3">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 4">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 4">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    $('#quiz-container').append(quizHTML);

});

$(document).on('click', '.delete-quiz-btn', function () {
    const quizItem = $(this).closest('.quiz-item');
    quizItem.remove();
    renumberQuizes();
});


$(document).on('change', '.answer-checkbox', function () {
    const quizId = $(this).attr('name');
    $(`input[name="${quizId}"]`).not(this).prop('checked', false);
});


const renumberQuizes = () => {
    let quizCount = 0;
    const quizzes = $('.quiz-item');

    //console.log(`Found ${quizzes.length} quiz items.`);

    quizzes.each(function () {
        quizCount++;
        $(this).attr('data-quiz-id', quizCount);
        $(this).find('.main-text').text(`Quiz ${quizCount}`);
        $(this).find('.answer-checkbox').attr('name', `quiz-${quizCount}`);
    });

    if (quizCount == 0) {
        $('#quiz-questions-container').addClass('d-none');
        $('#empty-question-display-area').removeClass('d-none');
    }
}

const downloadQuizCSVFormat = () => {
    const filePath = 'assets/uploads/csv-format/quizUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'quizUpload.csv';
    link.click();
}

const fileInput = $('#file-selector-input');
const uploadButton = $('.modal-footer .custom-btn');
const quizContainer = $('#quiz-container');
const fileFeedback = $('#file-feedback');

uploadButton.on('click', () => {
    const file = fileInput[0].files[0];
    if (file) {
        readCSVFile(file);
        $('#file-selector-input').val('');
        $("#quizBulkUpload").modal('hide');
    } else {
        fileFeedback.text('No file selected.');
    }
});

const readCSVFile = (file) => {
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const { headers, rows } = parseCSV(text);
        if (rows.length > 0) {
            populateQuizFields(headers, rows);
        } else {
            fileFeedback.text('The CSV file is empty or invalid.');
        }
    };
    reader.readAsText(file);
};

const parseCSV = (text) => {
    const lines = text.split('\n');
    const headers = ['Question', 'Answer 1', 'Answer 2', 'Answer 3', 'Answer 4', 'Correct Answer'];
    const rows = [];

    for (let i = 0; i < lines.length; i++) {
        const values = lines[i].split(',');
        if (values.length === headers.length) {
            const row = {};
            for (let j = 0; j < headers.length; j++) {
                row[headers[j]] = values[j].trim();
            }
            rows.push(row);
        }
    }
    return { headers, rows };
};

const populateQuizFields = (headers, rows) => {
    quizContainer.empty();
    if ($("#quiz-questions-container").hasClass('d-none')) { $("#quiz-questions-container").removeClass('d-none'); }
    rows.forEach((row, index) => {
        if (index != 0) {
            quizCount++;
            const quizHTML = `
                <div class="col-12 p-0 mb-3 quiz-item" data-quiz-id="${index}" data-quiz-status="2">
                    <div class="row p-0 m-0">
                        <div class="col-12 p-2 px-md-4 pe-md-4 pt-md-2 pb-md-2 d-flex p-0 m-0 align-items-center bl-solid" data-bs-toggle="collapse" data-bs-target="#quiz-body-${index}" aria-expanded="false" aria-controls="quiz-body-${index}">
                            <span class="text-16 main-text flex-grow-1">Quiz ${index}</span>
                            <div class="col-6 d-flex justify-content-end">
                                <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text delete-quiz-btn"></iconify-icon>
                            </div>
                        </div>
                        <div class="col-12 p-0 m-0 bl-solid-lrb p-4 accordion-collapse collapse" id="quiz-body-${index}" data-bs-parent="#quiz-container">
                            <div class="row gy-3 accordion-body">
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-16 sub-text">Question</label>
                                    <div class="d-flex align-items-center gx-3 mt-3">
                                        <div class="d-flex align-items-center flex-grow-1 pe-3">
                                            <input type="text" class="form-control custom-input question-input" placeholder="Enter question" value="${row['Question']}">
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <img src="./assets/imgs/common/vector.png" alt="add image question">
                                        </div>
                                    </div>
                                </div>
                                ${['Answer 1', 'Answer 2', 'Answer 3', 'Answer 4'].map((answer, i) => `
                                    <div class="col-12 col-md-6 d-flex align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${index}" value="Option ${i + 1}" ${row['Correct Answer'] === (i + 1).toString() ? 'checked' : ''}>
                                        <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="${answer}" value="${row[answer]}">
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            quizContainer.append(quizHTML);
            $("#empty-question-display-area").addClass('d-none');
        }
    });
};

$(document).on('change', '.answer-checkbox', function () {
    const quizId = $(this).attr('name');
    $(`input[name="${quizId}"]`).not(this).prop('checked', false);
});

$(document).on('click', '.delete-quiz-btn', function () {
    $(this).closest('.quiz-item').remove();
    renumberQuizes();
});

const handleQuizesPagination = (subjectId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterQuizes(subjectId);
}

const removeQuizes = [];

const deleteQuiz = (element) => {
    const quizItem = $(element).closest('.quiz-item');
    removeQuizes.push(quizItem.data('quiz-id'));
    quizItem.remove();
    quizCount = 0;
    $('.quiz-item').each(function () {
        quizCount++;
        $(this).find('.main-text').text(`Quiz ${quizCount}`);
        $(this).find('.answer-checkbox').attr('name', `quiz-${quizCount}`);
    });
    //console.log(removeQuizes);
}

const removeQuiz = (quizId) => {
    $('#removeConfirmModal').data('quiz-id', quizId);
    $("#removeConfirmModal").modal("show");
}

// Open full-screen image viewer
const openFullScreen = (imgPath) => {
    const $overlay = $('#fullScreenOverlay');
    const $fullScreenImage = $('#fullScreenImage');

    // Set the image source
    $fullScreenImage.attr('src', imgPath);

    // Set fixed dimensions (1200px width, 900px height)
    $fullScreenImage.css({
        'width': '1200px',
        'height': '900px',
        'object-fit': 'contain' // Ensures the image fits while maintaining aspect ratio
    });

    // Display the overlay
    $overlay.css('display', 'flex');

    // Enable dragging and zooming (if needed)
    enableDragAndZoom($fullScreenImage);
};
// Close full-screen image viewer
const closeFullScreen = () => {
    const $overlay = $('#fullScreenOverlay');
    $overlay.hide(); // Hide the overlay
};

// Enable dragging and zooming for the image
const enableDragAndZoom = ($image) => {
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    let scale = 1;

    // Mouse down event for dragging
    $image.on('mousedown', (e) => {
        isDragging = true;
        startX = e.clientX - translateX;
        startY = e.clientY - translateY;
        $image.css('cursor', 'grabbing'); // Change cursor to grabbing
    });

    // Mouse move event for dragging
    $image.on('mousemove', (e) => {
        if (isDragging) {
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            $image.css('transform', `translate(${translateX}px, ${translateY}px) scale(${scale})`);
        }
    });

    // Mouse up event to stop dragging
    $image.on('mouseup', () => {
        isDragging = false;
        $image.css('cursor', 'grab'); // Change cursor back to grab
    });

    // Mouse leave event to stop dragging
    $image.on('mouseleave', () => {
        isDragging = false;
        $image.css('cursor', 'grab'); // Change cursor back to grab
    });

    // Zoom in/out with mouse wheel
    $image.on('wheel', (e) => {
        e.preventDefault();
        if (e.originalEvent.deltaY < 0) {
            scale += 0.1; // Zoom in
        } else {
            scale -= 0.1; // Zoom out
        }
        scale = Math.min(Math.max(0.5, scale), 3); // Limit zoom range
        $image.css('transform', `translate(${translateX}px, ${translateY}px) scale(${scale})`);
    });
};


const openUpdatePaymentStatusModal = () => {
    $("#updatePaymentStatus").modal("show");
}

const handleSelectedPaymentCategoryPagination = (categoryId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterPayments(categoryId);
}

const openPaymentCategoryEditModal = (id, name, currency) => {
    //console.log(id, name);
    $("#edit-category-name").val(name);
    if (currency != null) {
        $("#edit-category-currency").val(currency);
    }
    $("#save-edit-category-btn").on("click", function () {
        editPaymentCategory(this, id);
    });

    $("#editPayment").modal("show");
}

const handleIntakeHasPaymentsPagination = (intakeId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage2 = pageId;
            break;
        case 'previousPage':
            if (selectedPage2 > 1) {
                selectedPage2--;
            }
            break;
        case 'nextPage':
            if (selectedPage2 < totalPages) {
                selectedPage2++;
            }
            break;
        case 'filter':
            selectedPage2 = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = 0;
            sortCondition1 = (sortCondition1 === 1 ? 2 : 1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = 0;
            sortCondition2 = (sortCondition2 == 1 ? 2 : 1);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition4 = 0;
            sortCondition3 = (sortCondition3 === 1 ? 2 : 1);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition3 = 0;
            sortCondition4 = (sortCondition4 === 1 ? 2 : 1);
            break;

    }

    filterIntakePayments(intakeId);
}

const handleSubjectAddStudentsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectAddStudents(subjectId);
}

const handleSubjectStudentsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectStudents(subjectId);
}

const assignmentRecorectionOnchange = () => {
    const checked = $("#recorection").prop("checked"); // Removed the colon

    if (checked) {
        $("#recorection-fee-add-area").removeClass("d-none");
    } else {
        $("#recorection-fee-add-area").addClass("d-none");
        $("#recorrection-fee").val('');
    }
}

const checkboxes = document.querySelectorAll('.lecture-type-checkbox');

checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function () {
        if (this.checked) {
            checkboxes.forEach(cb => {
                if (cb !== this) cb.checked = false;
            });

            const dependentField = document.getElementById(this.dataset.dependentField);
            if (dependentField) {
                if (this.dataset.dependentAction === 'show') {
                    dependentField.classList.remove('d-none');
                } else {
                    dependentField.classList.add('d-none');
                }
            }
        } else {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (!anyChecked) {
                this.checked = true;
            }
        }
    });
});

const initialChecked = document.querySelector('.lecture-type-checkbox:checked');
if (initialChecked) {
    const dependentField = document.getElementById(initialChecked.dataset.dependentField);
    if (dependentField && initialChecked.dataset.dependentAction === 'show') {
        dependentField.classList.remove('d-none');
    }
}

const handleSubjectHasClassSchedulePagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectScheduledLectures(subjectId);
}


const handleUsersPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterUsersPermissions();
}

const handleStudentsPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsPermissions();
}

const toggleCheckbox = (userId) => {
    let checkbox = document.getElementById('check-user' + userId);
    checkbox.checked = !checkbox.checked;
    checkRowEvents(userId);
}

let selectedLecturers = [];
let unSelectedLecturers = [];

const lecturersTableCheckEvents = () => {
    var isChecked = $("#lecturers-check-all").prop('checked');
    $('.lecturer-row-check').prop('checked', isChecked);
    if (isChecked) {
        selectedLecturers = [];
        unSelectedLecturers = [];
    } else {
        if (selectedLecturers.length == 0 || unSelectedLecturers.length == 0) {
            selectedLecturers = [];
            unSelectedLecturers = [];
        }
    }

}

const lecturerCheckRowEvents = (id) => {
    var isChecked = $("#check-lecturer" + id).prop('checked');

    //console.log(isChecked)

    if (isChecked) {
        if (!selectedLecturers.includes(id)) {
            if (unSelectedLecturers.length != 0 && unSelectedLecturers.includes(id)) {
                unSelectedLecturers = unSelectedLecturers.filter(userId => userId !== id);
            } else {
                selectedLecturers.push(id);
            }
        }
        $("#check-all").prop('checked', false);
    } else {
        $("#check-all").prop('checked', false);
        if (selectedLecturers.length != 0 && selectedLecturers.includes(id)) {
            selectedLecturers = selectedLecturers.filter(userId => userId !== id);
        } else {
            unSelectedLecturers.push(id);
        }
    }

    //console.log(selectedLecturers, unSelectedLecturers);
};

const handleLecturersPagination = (subjectId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterSubjectHasLecturers(subjectId);
}

const openRegisteredStudentAddIntakeModal = (id, intakeId, stuName) => {
    //console.log(id, intakeId, stuName);
    $("#student-id").val(id);
    $("#student-name").val(stuName);
    $("#add-registered-student-to-intake").attr("onclick", `addIntakeToRegisteredStudents(${id}, ${intakeId})`);

    $("#studentAddIntake").modal('show');
};

const handleRegisteredStudentsPagination = (intakeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterRegisteredStudents(intakeId);
}

const downloadIntakeStudentBulkUploadCSV = () => {
    const filePath = 'assets/uploads/csv-format/studentBulkUploadToIntake.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'studentData.csv';
    link.click();
}

const handleStudentsRecorrectionAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 == 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterRecorrectionRequestStudentsAssignments(assignmentId);
}

var faqAnswer;
var faqUpdatedAnswer;
const initializeFAQ = () => {
    faqAnswer = new RichTextEditor("#div_editor1");
}


const handleFAQPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterFAQ();
}


const initializeNews = () => {
    const container = document.querySelector('#comment-section');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

const handleSLPRecorrectionAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLPRecorrectionRequestStudentsAssignments(assignmentId);
}
const handleSLPAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLPStudentsAssignments(assignmentId);
}


const loadLecturerExamAddMarkModel = (examId, code, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark").attr("onclick", "addPhysicalExaminationMark('" + id + "','" + examId + "')");
    } else {
        $("#saveMark").attr("onclick", "addOnlineExaminationMark('" + id + "','" + examId + "')");
    }

    $("#addMark").modal('show');
}

const loadLecturerExamEditMarkModel = (examId, code, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editPhysicalExaminationMark('" + id + "','" + examId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editOnlineExaminationMark('" + id + "','" + examId + "')");
    }


    $("#editMark").modal('show');
}

const downloadExamMarksUploadCSV = (exam) => {
    const filePath = './assets/uploads/csv-format/assignmentMarksUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'ExaminationMarksUpload-Examination-' + exam + '.csv';
    link.click();
}


const handleStudentsExaminationssPagination = (examId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsExaminations(examId);
}

const handleStudentsQuizPagination = (quizId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsQuizzes(quizId);
}

const handleFacultiesPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterFacultiesPermissions();
}


const handlePaymentsPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterPaymentsPermissions();
}


const handlePortalPaymnetAddStudentsPagination = (action, pageId = null, totalPages = null) => {
    console.log(action);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    console.log(selectedPage);

    filterPortalPayments();
}


const handlePortalPaymentStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsPortalPayments();
}

const downloadPortalpaymentBulkUploadCSV = () => {
    const filePath = 'assets/uploads/csv-format/portalPaymentBulkUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'portalPaymentBulkUpload.csv';
    link.click();
}

const handleStudentsOngoingLectureAttendancePagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsOngoingLectureAttendance(lectureId);
}
const handleSubjectPrerecordedLecturesPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectPreRecordedLectures(subjectId);
}


const handleSubjectRecordingsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectRecordings(subjectId);
}

let collectedItems = [];

// Function to handle collect button click
function handleCollect(button, lecturersId, subjectAmount, count, subjectType) {
    const $button = $(button);

    // Change button to checked state
    $button.html('Collected')
        .removeClass('active')
        .addClass('table-btn-success')
        .off('click');

    // Create undo button
    const $undoBtn = $('<button>')
        .html('Undo')
        .addClass('table-btn table-btn-danger active ms-2')
        .on('click', function () {
            undoCollection($button, $undoBtn, lecturersId, subjectAmount, count, subjectType);
        });

    // Insert undo button after the collect button
    $button.after($undoBtn);

    // Store collected item data
    collectedItems.push({
        button: $button,
        undoBtn: $undoBtn,
        lecturersId: lecturersId,
        amount: subjectAmount,
        count: count,
        type: subjectType
    });

    // Update total collected amount display
    updateTotalCollected(subjectAmount, true);

}

// Function to undo a collection
function undoCollection($button, $undoBtn, lecturersId, subjectAmount, count, subjectType) {
    // Restore original collect button
    $button.html('+ Collect')
        .addClass('active')
        .on('click', function () {
            handleCollect(this, lecturersId, subjectAmount, count, subjectType);
        });

    // Remove undo button
    $undoBtn.remove();

    // Remove item from collectedItems array
    collectedItems = collectedItems.filter(item => item.lecturersId !== lecturersId);

    // Update total collected amount display
    updateTotalCollected(subjectAmount, false);

}

// Function to update the total collected amount display
function updateTotalCollected(amount, isAdd) {
    const totalElement = $('#totalCollected');
    const balanceElement = $('#totalBalance');
    hidePayoutButton();
    if (totalElement.length) {
        let currentTotal = parseFloat(totalElement.text().replace('LKR ', '').replace(/,/g, '')) || 0;
        currentTotal = isAdd ? currentTotal + parseFloat(amount) : currentTotal - parseFloat(amount);
        totalElement.text('LKR ' + currentTotal.toLocaleString('en-US'));

        if (currentTotal > 0) {
            showPayoutButton();
        }
    }

    if (balanceElement.length) {
        //console.log(amount);
        let currentBalance = parseFloat(balanceElement.text().replace('LKR ', '').replace(/,/g, '')) || 0;
        //console.log(currentBalance);
        //console.log(amount);

        currentBalance = isAdd ? currentBalance - parseFloat(amount) : currentBalance + parseFloat(amount);
        balanceElement.text('LKR ' + currentBalance.toLocaleString('en-US'));
    }
}

// Function to initialize collect buttons
function initCollectButtons() {
    $('.table-btn-success').off('click').each(function () {
        const $button = $(this);
        const lecturersId = $button.data('lecturer-id');
        const subjectAmount = $button.data('amount');
        const subjectType = $button.data('type');
        const count = $button.data('count');

        $button.on('click', function () {
            handleCollect(this, lecturersId, subjectAmount, count, subjectType);
        });
    });

}

const showPayoutButton = () => {
    const payoutHTML = `
        <div class="row justify-content-end gx-3 h-100">
            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                <button class="custom-btn w-100" onclick="payoutLecturerPayments();">Payout</button>
            </div>
        </div>
    `;
    $('#payoutBtnContainer').html(payoutHTML);
};

const hidePayoutButton = () => $('#payoutBtnContainer').html('');

const initPasswordSetup = (token, status) => {
    const form = document.getElementById('passwordSetupForm');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirm = document.getElementById('toggleConfirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    const strengthBar = document.getElementById('strengthBar');

    // Password toggle functionality
    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    toggleConfirm.addEventListener('click', function () {
        const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmInput.setAttribute('type', type);
        this.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    // Password strength checker
    passwordInput.addEventListener('input', function () {
        const password = this.value;
        const strength = calculateStrength(password);
        updateStrengthIndicator(strength);
    });


    confirmInput.addEventListener('input', validateForm);

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!submitBtn.disabled) {
            // Simulate form submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            setupUserPassword(token, status);
        }
    });

    function calculateStrength(password) {
        let strength = 0;

        // Length check
        if (password.length >= 8) strength += 20;
        if (password.length >= 12) strength += 20;

        // Character diversity
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[0-9]/.test(password)) strength += 20;
        if (/[^A-Za-z0-9]/.test(password)) strength += 20;

        return Math.min(strength, 100);
    }

    function updateStrengthIndicator(strength) {
        strengthBar.style.width = strength + '%';

        if (strength < 40) {
            strengthBar.style.backgroundColor = 'var(--error)';
        } else if (strength < 70) {
            strengthBar.style.backgroundColor = '#4e5d6f'; // orange
        } else {
            strengthBar.style.backgroundColor = 'var(--success)';
        }

        // Update password hints
        const hasLength = passwordInput.value.length >= 8;
        const hasNumber = /[0-9]/.test(passwordInput.value);
        const hasSpecial = /[^A-Za-z0-9]/.test(passwordInput.value);

        updateHint('lengthHint', hasLength);
        updateHint('numberHint', hasNumber);
        updateHint('specialHint', hasSpecial);
    }

    function updateHint(id, isValid) {
        const hint = document.getElementById(id);
        const icon = hint.querySelector('.hint-icon');

        if (isValid) {
            icon.textContent = '✓';
            icon.style.color = 'var(--success)';
        } else {
            icon.textContent = '✗';
            icon.style.color = 'var(--error)';
        }
    }

    function validateForm() {
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;
        let isValid = true;


        // Validate password
        const passwordError = document.getElementById('passwordError');
        if (password.length < 8 || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
            passwordError.style.display = 'block';
            isValid = false;
        } else {
            passwordError.style.display = 'none';
        }

        // Validate password match
        const confirmError = document.getElementById('confirmError');
        if (password !== confirmPassword || confirmPassword === '') {
            confirmError.style.display = 'block';
            isValid = false;
        } else {
            confirmError.style.display = 'none';
        }

        // Enable/disable submit button
        submitBtn.disabled = !isValid;

        return isValid;
    }
}

const initializeLecturerSchedule = () => {
    getLectureScheduleData();
}

const addNewProgrammeDuration = () => {
    $("#programme-duration-row").addClass("d-none");
    $("#new-programme-duration-row").removeClass("d-none");
}

const selectExistingProgrameDuration = () => {
    $("#programme-duration-row").removeClass("d-none");
    $("#new-programme-duration-row").addClass("d-none");
}

const addNewEditProgrammeDuration = () => {
    $("#edit-programme-duration-row").addClass("d-none");
    $("#edit-new-programme-duration-row").removeClass("d-none");
}

const selectExistingEditProgrameDuration = (value) => {
    $("#edit-programme-duration-row").removeClass("d-none");
    $("#edit-new-programme-duration-row").addClass("d-none");
    $("#edit-programme-duration").val(value);
}


const initializeSelectedIntake = (id) => {
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

    initializeSelectedIntakeAttendnace(id);


}

const openLectureModal = (lectureUrl) => {
    const $modalBody = $("#lectureModalBody");
    $modalBody.empty();

    // Create loading spinner
    const $spinner = $("<div>", {
        class: "spinner-border dark-theme",
        role: "status",
        css: {
            display: "block",
            margin: "50px auto"
        }
    }).append($("<span>", { class: "visually-hidden", text: "Loading..." }));

    $modalBody.append($spinner);

    // Create iframe
    const $iframe = $("<iframe>", {
        src: lectureUrl,
        width: "100%",
        height: "500px",
        frameborder: "0",
        allow: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture",
        allowfullscreen: true,
        css: { display: "none" } // hide until loaded
    });

    $iframe.on("load", function () {
        $spinner.remove();
        $(this).show();
    });

    $modalBody.append($iframe);

    // Show modal
    const lectureModalEl = document.getElementById("lectureView");
    const lectureModal = new bootstrap.Modal(lectureModalEl);
    lectureModal.show();

    // Stop video when modal closes
    $(lectureModalEl).on("hidden.bs.modal", function () {
        $iframe.remove(); // remove iframe to stop video
    });
};

const handleStudentsQuizResetRequestPagination = (quizId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsQuizResetRequests(quizId);
}

const handleSubjectLectureAttendancePagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition2 = sortCondition2 === 1 ? 2 : 1;
            sortCondition1 = 0;
            break;
    }

    filterSubjectLectureAttendance(lectureId);
}

const openModelEditLecture = (name, hourlyRate, assignmentFee, examinationFee, lecturerId) => {
    $("#lecturer-name").val(name);

    $("#edit-lecturer-hourly-rate").val(hourlyRate);
    $("#edit-assignment-fee").val(assignmentFee);
    $("#edit-examination-fee").val(examinationFee);

    $("#editLecturerBtn").off("click").on("click", function () {
        editSubjectAssignLecturer(this, lecturerId);
    });

    $("#editLecturer").modal("show");
}


const openUpdateInquiryStatusModal = () => {
    $("#updateInquiryStatus").modal("show");
}

const handleInquiriesPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterInquiries();
}


const initializeStudentInquiry = () => {
    const container = document.getElementById("userSelect");
    const display = container.querySelector(".custom-select-display");
    const options = container.querySelector(".custom-options");
    const searchInput = options.querySelector("input");
    const items = options.querySelectorAll("li");
    const hiddenInput = document.getElementById("userHidden");

    // Toggle dropdown
    display.addEventListener("click", () => {
        options.classList.toggle("show");
        searchInput.focus();
    });

    // Filter options
    searchInput.addEventListener("keyup", () => {
        let filter = searchInput.value.toLowerCase();
        items.forEach(item => {
            let text = item.textContent.toLowerCase();
            item.style.display = text.includes(filter) ? "block" : "none";
        });
    });

    // Select option
    items.forEach(item => {
        item.addEventListener("click", () => {
            display.textContent = item.textContent;
            hiddenInput.value = item.dataset.value;
            options.classList.remove("show");
        });
    });

    // Close if clicked outside
    document.addEventListener("click", e => {
        if (!container.contains(e.target)) {
            options.classList.remove("show");
        }
    });
}

const openAssignUserModal = () => {
    $("#assignUser").modal("show");
}

const handleLectureRecordingPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }
    filterLectureRecordings();
}

const handleReCorrectionAssignmentLecturersPayments = (id, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterReCorrectionAssignmentLecturersPayments(id);
}

const handleLecturersPaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersPayments();
}

const handleAssignmentLecturersPayments = (id, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterAssignmentLecturersPayments(id);
}

const handleLecturersAssignmentsPaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersAssignmentsPayments();
}

const handleLecturersLecturePaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersLecturePayments();
}

const handleLecturersPayoutRequestPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersPayoutRequest();
}

const viewLectureRecording = (link) => {
    $("#lectureRecordingModalBody").html(link);
    $("#lectureRecordingModal").modal('show');
}

const initializeSubjectLecturerSchedule = (id) => {
    getSubjectLectureScheduleData(id);
}

const handlePortalSubscribedStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterPortalSubscribedStudents();
}

const copyToClipboardIntakeStudentId = (id, icon) => {
    navigator.clipboard.writeText(id);
    icon.setAttribute('icon', 'lucide:copy-check');
}

const handleLectureSchedulePermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLectureSchedulePermissions();
}

const searchModal = document.getElementById('searchModal');

searchModal.addEventListener('hidden.bs.modal', function () {
    document.getElementById('live-search-input').value = '';
    renderSearchHistory();
});

const moveOnSearchResult = (text, link) => {
    storeSearchTerm(text, link);
    $("#live-search-input").val('');
    window.location.href = link;
}

function storeSearchTerm(term, link) {
    if (!term.trim()) return;

    const MAX_HISTORY = 5;
    let history = JSON.parse(localStorage.getItem("recentSearches") || "[]");

    history = history.filter(item => item.text.toLowerCase() !== term.toLowerCase());

    history.unshift({ text: term, link: link });

    if (history.length > MAX_HISTORY) history = history.slice(0, MAX_HISTORY);

    localStorage.setItem("recentSearches", JSON.stringify(history));
}

function renderSearchHistory() {
    const history = JSON.parse(localStorage.getItem("recentSearches") || "[]");
    const container = document.querySelector('#live-search-results');
    container.innerHTML = '';

    if (history.length === 0) {
        const no_recent = `
        <div class="d-flex align-items-center justify-content-center py-3">
            <h6 class="text-muted text-16 fw-semibold mb-3">No recent searches</h5>
        </div>
        `;

        $("#live-search-results").html(no_recent);
        return;
    }

    const modal_body = `
                    <div id="search-results" class="list-group mt-2">
                        <h6 class="dark-theme text-16 fw-semibold mb-3">Recent</h6>
                        ${history.map((item, index) => `
                             <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 bl-solid rounded-0 hover-bg">
                                <div class="icon-container d-flex align-items-center justify-content-center" onclick="window.location.href='${item.link}'">
                                    <iconify-icon icon="mdi:recent" width="20" height="20"></iconify-icon>
                                </div>

                                <div class="flex-grow-1 d-flex align-items-center" onclick="window.location.href='${item.link}'">
                                    <div class="fw-semibold text-dark-color px-3">${item.text}</div>
                                </div>
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <iconify-icon icon="material-symbols-light:close" width="20" height="20" class="cursor-pointer" onclick="removeSearchItem('${item.text}')"></iconify-icon>
                                </div>
                            </div>
                            `).join('')}
                       
                    </div>
    `;

    $("#live-search-results").html(modal_body);

}

function removeSearchItem(term) {
    let history = JSON.parse(localStorage.getItem("recentSearches") || "[]");
    history = history.filter(item => item.text !== term);
    localStorage.setItem("recentSearches", JSON.stringify(history));
    renderSearchHistory();
}

const gradeTypeChanged = (select) => {
    if (select.value == 2) {
        if ($("#category-grade-type").hasClass("d-none")) {
            $("#category-grade-type").removeClass("d-none");
        }

        if ($("#rubrics-grade-type").hasClass("d-none") == false) {
            $("#rubrics-grade-type").addClass("d-none");
        }
    }

    if (select.value == 4) {
        $("#rubrics-container").html('');
        if ($("#category-grade-type").hasClass("d-none") == false) {
            $("#category-grade-type").addClass("d-none");
        }

        if ($("#rubrics-grade-type").hasClass("d-none")) {
            $("#rubrics-grade-type").removeClass("d-none");
            addNewRubric();
        }
    }
}

const initializeAddSubjectGradeCalculationStructure = () => {
    const container = document.getElementById("grade-scales-container");
    const addBtn = document.getElementById("add-new-grade-scale");

    const defaultGradeScales = [
        { min: 80, max: 100, grade: "A", gpa: 4.0 },
        { min: 65, max: 79, grade: "B", gpa: 3.0 },
        { min: 50, max: 64, grade: "C", gpa: 2.0 },
        { min: 35, max: 49, grade: "D", gpa: 1.0 },
        { min: 0, max: 34, grade: "F", gpa: 0.0 }
    ];

    const createGradeScaleBlock = (index, data = null) => {
        const newBlock = document.createElement("div");
        newBlock.classList.add("col-12", "mt-4");

        newBlock.innerHTML = `
            <div class="row bl-solid p-4 m-0 gy-3 grade-scale-block">
                <div class="d-flex justify-content-between p-0 m-0">
                    <span class="text-14 main-text p-0 m-0 grade-scale-title">Grade Scale - ${index}</span>
                    <iconify-icon class="remove-grade-scale-icon cursor-pointer ${index == 1 ? "d-none" : ""}"
                        icon="material-symbols:delete-outline-sharp"
                        width="24" height="24">
                    </iconify-icon>
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Minimum Mark</label>
                    <input type="number" name="minMark" class="form-control custom-input mt-2"
                        value="${data ? data.min : ""}" placeholder="e.g. 40" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Maximum Marks</label>
                    <input type="number" name="maxMark" class="form-control custom-input mt-2"
                        value="${data ? data.max : ""}" placeholder="e.g. 100" min="1" step="1" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Grade</label>
                    <input type="text" name="grade" class="form-control custom-input mt-2"
                        value="${data ? data.grade : ""}" placeholder="e.g. A / Pass" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text not-required">GPA</label>
                    <input type="number" name="gpa" class="form-control custom-input mt-2"
                        value="${data ? data.gpa : ""}" placeholder="e.g. 4.0" step="0.1" />
                </div>
            </div>
        `;

        return newBlock;
    };

    defaultGradeScales.forEach((scale, index) => {
        const block = createGradeScaleBlock(index + 1, scale);
        container.appendChild(block);
    });


    addBtn.addEventListener("click", function () {
        const index = container.children.length + 1;
        const block = createGradeScaleBlock(index);
        container.appendChild(block);
        updateScaleTitles();
    });

    container.addEventListener("click", function (e) {
        if (e.target.closest(".remove-grade-scale-icon")) {
            const block = e.target.closest(".grade-scale-block");
            block.parentElement.remove();
            updateScaleTitles();
        }
    });

    function updateScaleTitles() {
        const titles = container.querySelectorAll(".grade-scale-title");
        titles.forEach((title, i) => {
            title.textContent = `Grade Scale - ${i + 1}`;
        });
    }
};

document.addEventListener("click", function (event) {

    if (event.target.closest(".remove-rubric")) {
        event.target.closest(".rubric-block").remove();
        reIndexRubrics();
    }

    if (event.target.closest(".add-rubric-criteria")) {
        const rubricIndex = event.target.closest(".add-rubric-criteria").dataset.rubric;
        const container = document.getElementById(`rubric-${rubricIndex}-criterias`);
        const count = container.querySelectorAll(".rubric-criteria-block").length + 1;

        container.insertAdjacentHTML(
            "beforeend",
            createCriteriaBlock(rubricIndex, count)
        );
        reIndexCriteria(rubricIndex);
    }

    if (event.target.closest(".remove-rubric-criteria")) {
        const rubricIndex = event.target.closest(".remove-rubric-criteria")
            .closest(".rubric-block").dataset.index;

        event.target.closest(".rubric-criteria-block").remove();
        reIndexCriteria(rubricIndex);
    }

});


const addNewRubric = () => {
    const container = document.getElementById("rubrics-container");
    const index = container.querySelectorAll(".rubric-block").length + 1;

    const rubricBlock = createRubricBlock(index);
    container.insertAdjacentHTML("beforeend", rubricBlock);

    reIndexRubrics();
};

function createRubricBlock(index) {
    return `
        <div class="col-12 rubric-block mt-4" id="rubric-${index}" data-index="${index}">
            <div class="row bl-solid p-4 m-0 gy-3">

                <div class="d-flex justify-content-between">
                    <span class="text-14 main-text rubric-title">Rubric - ${index}</span>
                    <iconify-icon class="remove-rubric cursor-pointer ${index == 1 ? "d-none" : ""}"
                        icon="material-symbols:delete-outline-sharp"
                        width="24" height="24"></iconify-icon>
                </div>

                <div class="col-12 col-lg-9">
                    <label class="text-14 main-text required">Rubric Name</label>
                    <input type="text" class="form-control custom-input mt-2"
                        id="rubric-name-${index}" placeholder="e.g. Group Project">
                </div>

                <div class="col-12 col-lg-3">
                    <label class="text-14 main-text required">Weight</label>
                    <input type="number" class="form-control custom-input mt-2"
                        id="rubric-weight-${index}" placeholder="e.g. 80" min="1">
                </div>

                <div class="d-flex justify-content-end">
                    <div class="d-flex align-items-center cursor-pointer add-rubric-criteria" 
                        data-rubric="${index}">
                        <iconify-icon icon="gridicons:add-outline" width="30" height="30"
                            class="dark-theme"></iconify-icon>
                        <span class="text-14 dark-theme px-2 pe-0">Add New Rubric Criteria</span>
                    </div>
                </div>

                <div class="col-12 rubric-criteria-container" id="rubric-${index}-criterias">
                    ${createCriteriaBlock(index, 1)}
                </div>
            </div>
        </div>
    `;
}


function createCriteriaBlock(rubricIndex, criteriaIndex) {
    return `
        <div class="row bl-solid p-4 mt-4 gy-3 rubric-criteria-block">
            <div class="d-flex justify-content-between">
                <span class="text-14 main-text criteria-title">
                    Rubric Criteria - ${criteriaIndex}
                </span>
                <iconify-icon class="remove-rubric-criteria cursor-pointer ${criteriaIndex == 1 ? "d-none" : ""}"
                    icon="material-symbols:delete-outline-sharp"
                    width="24" height="24"></iconify-icon>
            </div>

            <div class="col-12 col-lg-6">
                <label class="text-14 main-text required">Criteria Name</label>
                <input type="text" class="form-control custom-input mt-2"
                    placeholder="e.g. Communication Skills">
            </div>

            <div class="col-12 col-lg-3">
                <label class="text-14 main-text required">Weight</label>
                <input type="number" class="form-control custom-input mt-2"
                    placeholder="e.g. 10">
            </div>

            <div class="col-12 col-lg-3">
                <label class="text-14 main-text required">Maximum Mark</label>
                <input type="number" class="form-control custom-input mt-2"
                    placeholder="e.g. 20">
            </div>
        </div>
    `;
}

function reIndexRubrics() {
    document.querySelectorAll(".rubric-block").forEach((block, i) => {
        const newIndex = i + 1;
        block.dataset.index = newIndex;
        block.id = `rubric-${newIndex}`;

        block.querySelector(".rubric-title").textContent = `Rubric - ${newIndex}`;
        block.querySelector(".add-rubric-criteria").dataset.rubric = newIndex;

        block.querySelector(".remove-rubric").classList.toggle("d-none", newIndex === 1);

        reIndexCriteria(newIndex);
    });
}

function reIndexCriteria(rubricIndex) {
    const container = document.getElementById(`rubric-${rubricIndex}-criterias`);
    const criteriaBlocks = container.querySelectorAll(".rubric-criteria-block");

    criteriaBlocks.forEach((block, i) => {
        const newIndex = i + 1;
        block.querySelector(".criteria-title").textContent =
            `Rubric Criteria - ${newIndex}`;

        const deleteBtn = block.querySelector(".remove-rubric-criteria");
        deleteBtn.classList.toggle("d-none", newIndex === 1);
    });
}


function getGradeScalesData() {
    const container = document.getElementById("grade-scales-container");
    const blocks = container.querySelectorAll(".grade-scale-block");
    const gradeScales = [];

    blocks.forEach(block => {
        const minMark = block.querySelector('input[name="minMark"]').value;
        const maxMark = block.querySelector('input[name="maxMark"]').value;
        const grade = block.querySelector('input[name="grade"]').value;
        const gpaInput = block.querySelector('input[name="gpa"]');
        const gpa = gpaInput ? gpaInput.value : "";

        // Optionally convert to number where appropriate
        gradeScales.push({
            minMark: minMark === "" ? null : Number(minMark),
            maxMark: maxMark === "" ? null : Number(maxMark),
            grade: grade.trim(),
            gpa: gpa === "" ? null : Number(gpa)
        });
    });

    return gradeScales;
}

function getRubricData() {
    const container = document.getElementById("rubrics-container");
    const rubricBlocks = container.querySelectorAll(".rubric-block");
    const rubricData = [];

    rubricBlocks.forEach((rubric, rIndex) => {
        const rubricIndex = rIndex + 1;

        const rubricName = rubric.querySelector(`#rubric-name-${rubricIndex}`).value.trim();
        const rubricWeight = rubric.querySelector(`#rubric-weight-${rubricIndex}`).value.trim();

        const criteriaContainer = rubric.querySelector(`#rubric-${rubricIndex}-criterias`);
        const criteriaBlocks = criteriaContainer.querySelectorAll(".rubric-criteria-block");

        const criteriaData = [];

        criteriaBlocks.forEach((criteria, cIndex) => {
            const inputs = criteria.querySelectorAll("input");

            criteriaData.push({
                criteriaName: inputs[0].value.trim(),
                weight: Number(inputs[1].value) || null,
                maxMark: Number(inputs[2].value) || null
            });
        });

        rubricData.push({
            rubricName: rubricName,
            rubricWeight: Number(rubricWeight) || null,
            criterias: criteriaData
        });
    });

    return rubricData;
}

function getCategoryGradeValues() {
    const inputs = document.querySelectorAll('#category-grade-type input');
    const results = [];

    inputs.forEach(input => {
        const id = input.id;
        const value = input.value.trim();

        const categoryId = id.split("-")[2];

        results.push({
            category_id: Number(categoryId),
            weight: value === "" ? null : Number(value)
        });
    });

    return results;
}

const changeDiscountType = (select) => {
    if (select.value == 1) {
        if ($('#discount-area').hasClass('d-none') == false) {
            $('#discount-area').addClass('d-none');
            $('#discount-note-area').addClass('d-none');
        }
    } else {
        if ($('#discount-area').hasClass('d-none')) {
            $('#discount-area').removeClass('d-none');
            $('#discount-note-area').removeClass('d-none');
        }
    }
}

const openEditPaymentTypeModal = (id, name) => {
    //console.log(id, name);
    $("#edit-type-name").val(name);

    $("#save-edit-type").on("click", function () {
        editPaymentType(this, id);
    });

    $("#editPaymentType").modal("show");
}

const changePaneltyType = (select) => {
    if (select.value == 1) {
        if ($("#days-area").hasClass('d-none') == false) {
            $("#days-area").addClass('d-none');
        }
        if ($("#edit-days-area").hasClass('d-none') == false) {
            $("#edit-days-area").addClass('d-none');
        }
    } else if (select.value == 2) {
        if ($("#days-area").hasClass('d-none')) {
            $("#days-area").removeClass('d-none');
        }
        if ($("#edit-days-area").hasClass('d-none')) {
            $("#edit-days-area").removeClass('d-none');
        }
    }
}

const changePaneltyPaymentType = (select) => {
    if (select.value == 2) {
        if ($("#currency-area").hasClass('d-none') == false) {
            $("#currency-area").addClass('d-none');
        }

        $("#amount-label").text("Precentage");

    } else if (select.value == 1) {
        if ($("#currency-area").hasClass('d-none')) {
            $("#currency-area").removeClass('d-none');
        }

        $("#amount-label").text("Amount");
    }
}

const editChangePaneltyType = (select) => {
    if (select.value == 1) {

        if ($("#edit-days-area").hasClass('d-none') == false) {
            $("#edit-days-area").addClass('d-none');
        }
    } else if (select.value == 2) {

        if ($("#edit-days-area").hasClass('d-none')) {
            $("#edit-days-area").removeClass('d-none');
        }
    }
}

const editChangePaneltyPaymentType = (select) => {
    if (select.value == 2) {
        if ($("#edit-currency-area").hasClass('d-none') == false) {
            $("#edit-currency-area").addClass('d-none');
        }
        $("#edit-amount-label").text("Precentage");

    } else if (select.value == 1) {

        if ($("#edit-currency-area").hasClass('d-none')) {
            $("#edit-currency-area").removeClass('d-none');
        }
        $("#edit-amount-label").text("Amount");
    }
}

const openPaneltyPaymentCategoryEditModal = (data) => {
    $("#edit-option").val(data.panelty_payment_option_id);
    $("#edit-type").val(data.panelty_payments_type_id);
    $("#edit-currency").val(data.currency_id);
    $("#edit-panelty-amount").val(data.panelty_amount);
    $("#edit-panelty-days").val(data.due_dates);

    if (data.panelty_payments_type_id == 1) {
        $("#edit-currency-area").removeClass('d-none');
    }

    if (data.panelty_payment_option_id == 2) {
        $("#edit-days-area").removeClass('d-none');
    }

    $("#edit-panelty-type-btn").on("click", function () {
        editPaneltyPaymentCategory(this, data.panelty_payment_category_id);
    });

    $("#editPaneltyType").modal('show');
}

$(document).on("contextmenu", "#intake-row", function (e) {
    e.preventDefault();
    const intakeId = $(this).data("intake-id");
    openContextMenu(e, intakeId);
});

const openContextMenu = (e, intakeId) => {
    e.preventDefault();

    $("#custom-context-menu")
        .css({
            top: e.pageY,
            left: e.pageX
        })
        .removeClass("d-none");

    $("#duplicate-intake").on("click", function () {
        duplicateIntake(intakeId);
    });
};


let selectedCategories = [];

const checkPaneltyCategory = (input, categoryId) => {
    const optionType = input.dataset.option;
    if (optionType === "1") {

        if (input.checked) {

            document.querySelectorAll('input[data-option]').forEach(cb => {
                cb.checked = false;
            });

            input.checked = true;
            selectedCategories = [categoryId];

        } else {
            selectedCategories = [];
        }
    }

    else if (optionType === "2") {

        const currentDate = input.dataset.dates;

        document.querySelectorAll('input[data-option="1"]').forEach(cb => {
            cb.checked = false;
        });

        selectedCategories = selectedCategories.filter(id => {
            const el = document.querySelector(`input[data-category-id="${id}"]`);
            return el && el.dataset.option === "2";
        });

        if (input.checked) {
            document.querySelectorAll(
                `input[data-option="2"][data-dates="${currentDate}"]`
            ).forEach(cb => {
                if (cb !== input) {
                    cb.checked = false;

                    const removeId = parseInt(cb.getAttribute("data-category-id"));
                    selectedCategories = selectedCategories.filter(id => id !== removeId);
                }
            });

            if (!selectedCategories.includes(categoryId)) {
                selectedCategories.push(categoryId);
            }

        } else {
            selectedCategories = selectedCategories.filter(id => id !== categoryId);
        }
    }

    console.log(selectedCategories);
};

function viewPaymentPlanHasPaymentTypes(planId) {
    const allAreas = document.querySelectorAll('.payment-types-display-area');
    allAreas.forEach(area => {
        const areaPlanId = area.getAttribute('data-plan');

        if (areaPlanId == planId) {
            area.classList.remove('d-none');
        } else {
            area.classList.add('d-none');
        }
    });
}

const openAddPaymentTypeModal = (planId, planName) => {
    $("#plan-name").val(planName);

    $('#add-payment-type').off('click');
    $('#add-payment-type').on('click', function () {
        addPaymentType(this, planId);
    });

    $("#addPaymentType").modal('show');
}

function enablePanelty(checkbox) {
    const installmentId = checkbox.dataset.installmentId;

    const optionArea = document.querySelector(
        `#panelty-option-area[data-installment-id="${installmentId}"]`
    );

    if (checkbox.checked) {
        optionArea.classList.remove("d-none");
    } else {
        optionArea.classList.add("d-none");

        hideFlatAndCustom(installmentId);
    }
}


function paneltyOptionChange(select) {
    const installmentId = select.dataset.installmentId;
    const selected = select.value;

    hideFlatAndCustom(installmentId);

    if (selected === "2" || selected === "3") {
        // Flat or Per Day
        document
            .querySelector(`#panelty-flat-area[data-installment-id="${installmentId}"]`)
            .classList.remove("d-none");
    }

    if (selected === "4") {
        // Custom
        document
            .querySelector(`#panelty-custom-area[data-installment-id="${installmentId}"]`)
            .classList.remove("d-none");
    }
}

function hideFlatAndCustom(installmentId) {
    document
        .querySelector(`#panelty-flat-area[data-installment-id="${installmentId}"]`)
        ?.classList.add("d-none");

    document
        .querySelector(`#panelty-custom-area[data-installment-id="${installmentId}"]`)
        ?.classList.add("d-none");
}
function addNewCustomPaneltyArea(btn, installmentId) {
    const wrapper = btn.closest('#panelty-custom-area');
    const container = wrapper.querySelector('#custom-slots');

    const count = container.querySelectorAll('.custom-slot').length + 1;

    const html = `
    <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">

        <div class="row p-4 gy-3 gx-3 align-content-center ">
            <div class="d-flex justify-content-between">
                <span class="text-14 sub-topic">Custom Date Slot - ${count}</span>
                <iconify-icon icon="carbon:close-filled"
                    width="20"
                    height="20"
                    class="main-text cursor pointor"
                
                    onclick="removeCustomPanelty(this)">
                </iconify-icon>
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Panelty Amount Type</label>
                <select class="form-select custom-select mt-2 panelty-type">
                    <option value="0">- Select -</option>
                    <option value="1">Fixed</option>
                    <option value="2">Percentage</option>
                </select>
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Amount</label>
                <input type="number" class="form-control custom-input mt-2 panelty-amount">
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Days</label>
                <input type="number" class="form-control custom-input mt-2 panelty-days">
            </div>
        </div>
        </div>
    `;

    container.insertAdjacentHTML("beforeend", html);
}
function removeCustomPanelty(icon) {
    icon.closest(".custom-slot").remove();
}

function changenstallmentDiscountType(select) {
    const installmentId = select.dataset.installmentId;
    const value = select.value;

    // Find the correct accordion item
    const accordionItem = select.closest('.accordion-item');

    const amountArea = accordionItem.querySelector('#discount-amount-area');
    const noteArea = accordionItem.querySelector('#discount-note-area');

    // Hide by default
    amountArea.classList.add('d-none');
    noteArea.classList.add('d-none');

    // Reset values when hidden
    accordionItem.querySelector('#discount').value = '';
    accordionItem.querySelector('#discount-note').value = '';

    // Show only for discount types 2 or 3
    if (value === '2' || value === '3') {
        amountArea.classList.remove('d-none');
        noteArea.classList.remove('d-none');
    }
}

let planHasPaymentTypes = [];
let maximumAllowedPayments = 0;

function addNewPaymentArea() {
    const container = document.getElementById("fees-type");

    const nextId = container.children.length + 1;

    const paymentHTML = `
    <div class="accordion-item br-0" data-installment-id="${nextId}">
                                                                    <h2 class="accordion-header br-0" id="headingOne${nextId}">
                                                                        <button class="accordion-button br-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne${nextId}" aria-expanded="false" aria-controls="collapseOne${nextId}">
                                                                             <span class="flex-grow-1 payment-btn-text">Payment - ${nextId}</span>
                                                                            <iconify-icon
                                                                                icon="material-symbols:delete-outline-rounded"
                                                                                width="22"
                                                                                height="22"
                                                                                class="text-danger cursor-pointer"
                                                                                onclick="removePaymentArea(this)">
                                                                            </iconify-icon>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="collapseOne${nextId}" class="accordion-collapse collapse" aria-labelledby="headingOne${nextId}" data-bs-parent="#fees-type">
                                                                        <div class="accordion-body">
                                                                            <div class="col-12 component-theme">
                                                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Payment Type</label>
                                                                                        <select id="payment-type" data-installment-id="${nextId}" class="form-select custom-select mt-2 payment-type">
                                                                                            <option selected value="0">- Select -</option>
                                                                                        </select>
                                                                                        <span id="payment-type-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label for="" class="text-14 main-text required">Amount</label>
                                                                                        <input type="number" class="form-control custom-input mt-2" id="amount">
                                                                                        <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Discount Type</label>
                                                                                        <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="${nextId}" onchange="changenstallmentDiscountType(this)">
                                                                                            <option selected value="1">No Discount</option>
                                                                                            <option value="2">Fixed Amount Discount</option>
                                                                                            <option value="3">Percentage Based Discount</option>
                                                                                        </select>
                                                                                        <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column d-none" id="discount-amount-area">
                                                                                        <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                        <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                                        <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                                        <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                        <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                        <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                        <input type="date" class="form-control custom-input mt-2" id="due-date" min="0">
                                                                                        <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 d-flex align-items-center">
                                                                                        <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" data-installment-id="${nextId}" onchange="enablePanelty(this)">
                                                                                        <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                    </div>

                                                                                    <div class="col-12 col-lg-4 d-flex flex-column d-none" data-installment-id="${nextId}" id="panelty-option-area">
                                                                                        <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                        <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="${nextId}" onchange="paneltyOptionChange(this)">
                                                                                            <option selected value="0">- Select -</option>
                                                                                            <option value="2">Flat Amount</option>
                                                                                            <option value="3">Percentage</option>
                                                                                            <option value="4">Costom Slots</option>
                                                                                        </select>
                                                                                        <span id="panelty-option-feedback" data-installment-id="${nextId}" class="text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 d-none" id="panelty-flat-area" data-installment-id="${nextId}">
                                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                    <option value="0">- Select -</option>
                                                                                                    <option value="1">Fixed Amount</option>
                                                                                                    <option value="2">Percentage</option>
                                                                                                </select>
                                                                                                <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                <input type="number" class="form-control custom-input mt-2" id="flat-amount">
                                                                                                <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="col-12 d-flex flex-column d-none" id="panelty-custom-area" data-installment-id="${nextId}">
                                                                                        <div class="row p-0 m-0" id="custom-slots">

                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                    <div class="d-flex justify-content-between">
                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - 1</span>

                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                            <option value="0">- Select -</option>
                                                                                                            <option value="1">Fixed</option>
                                                                                                            <option value="2">Percentage</option>
                                                                                                        </select>
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3">
                                                                                            <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, ${nextId});">Add New</button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>`;

    container.insertAdjacentHTML("beforeend", paymentHTML);

    loadPaymentTypesForInstallment(nextId);

    if (nextId + 1 > maximumAllowedPayments) {
        $("#add-new-payment-area").hasClass('d-none') ? '' : $("#add-new-payment-area").addClass('d-none');
    }
}

function loadPaymentTypesForInstallment(installmentId) {
    const select = document.querySelector(
        `.payment-type[data-installment-id="${installmentId}"]`
    );

    if (!select) return;

    let options = `<option value="0">- Select -</option>`;

    planHasPaymentTypes.forEach(type => {
        options += `<option value="${type.payment_type_id}">
                        ${type.payment_type}
                    </option>`;
    });

    select.innerHTML = options;
}
function removePaymentArea(icon) {
    const item = icon.closest(".accordion-item");
    item.remove();
    reindexPayments();
}
function reindexPayments() {
    const items = document.querySelectorAll("#fees-type .accordion-item");

    items.forEach((item, index) => {
        const newId = index + 1;

        item.setAttribute("data-installment-id", newId);
        item.querySelector(".payment-btn-text").innerText = `Payment - ${newId}`;
    });

    if (items.length < maximumAllowedPayments) {
        $("#add-new-payment-area").hasClass('d-none') ? $("#add-new-payment-area").removeClass('d-none') : '';
    }
}

function collectInstallmentData() {
    const data = [];
    document.querySelectorAll(".accordion-item").forEach(item => {
        const installmentId =
            item.querySelector("#enable-panelty")?.dataset.installmentId;

        const installment = {
            installmentId,
            paymentType: item.querySelector("#payment-type")?.value,
            amount: item.querySelector("#amount")?.value,
            discountType: item.querySelector("#discount-type")?.value,
            discount: item.querySelector("#discount")?.value,
            discountNote: item.querySelector("#discount-note")?.value,
            dueDate: item.querySelector("#due-date")?.value,
            penaltyEnabled: item.querySelector("#enable-panelty")?.checked,
            penalty: null
        };

        if (installment.penaltyEnabled) {
            const option = item.querySelector("#panelty-option")?.value;

            if (option === "2" || option === "3") {
                installment.penalty = {
                    mode: option,
                    type: item.querySelector("#flat-type")?.value,
                    amount: item.querySelector("#flat-amount")?.value,
                };
            }

            if (option === "4") {
                installment.penalty = {
                    mode: option,
                    slots: []
                };

                item.querySelectorAll(".custom-slot").forEach(slot => {
                    installment.penalty.slots.push({
                        type: slot.querySelector(".panelty-type")?.value,
                        amount: slot.querySelector(".panelty-amount")?.value,
                        days: slot.querySelector(".panelty-days")?.value
                    });
                });
            }
        }

        data.push(installment);
    });

    return data;
}


function generatePaymentErrorPDF(errorData) {

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    let y = 15;

    doc.setFontSize(18);
    doc.text('Payment Validation Errors', 14, y);
    y += 8;

    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, y);
    y += 10;

    errorData.installments.forEach((installment, index) => {

        doc.setFontSize(14);
        doc.text(`Fees Type : ${installment.installmentId}`, 14, y);
        y += 6;

        const tableBody = [];

        Object.entries(installment.errors).forEach(([field, error]) => {

            if (field === 'panelty') {
                Object.entries(error).forEach(([key, msg]) => {
                    const slotNo = parseInt(key.split('.')[1]) + 1;
                    tableBody.push([
                        `Penalty Slot ${slotNo}`,
                        msg
                    ]);
                });
            } else {
                tableBody.push([
                    field.replace(/([A-Z])/g, ' $1'),
                    error
                ]);
            }
        });

        doc.autoTable({
            startY: y,
            head: [['Field', 'Error Message']],
            body: tableBody,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: { fillColor: [220, 220, 220] }
        });

        y = doc.lastAutoTable.finalY + 10;
    });

    doc.output('bloburl');
    window.open(doc.output('bloburl'), '_blank');
}

function generateUpdatePaymentErrorPDF(errorData) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    let y = 15;

    // Header
    doc.setFontSize(18);
    doc.text('Payment Validation Errors', 14, y);
    y += 8;

    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, y);
    y += 10;

    // Iterate installments object
    Object.entries(errorData.installments).forEach(([key, installment], index) => {
        doc.setFontSize(14);
        doc.text(`Fees Type: ${installment.installmentId || key}`, 14, y);
        y += 6;

        const tableBody = [];

        // Iterate errors inside each installment
        Object.entries(installment.errors).forEach(([field, error]) => {
            if (field === 'panelty') {
                Object.entries(error).forEach(([slotKey, msg]) => {
                    const slotNo = parseInt(slotKey.split('.')[1]) + 1;
                    tableBody.push([`Penalty Slot ${slotNo}`, msg]);
                });
            } else {
                tableBody.push([field.replace(/([A-Z])/g, ' $1'), error]);
            }
        });

        // Generate table
        doc.autoTable({
            startY: y,
            head: [['Field', 'Error Message']],
            body: tableBody,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: { fillColor: [220, 220, 220] }
        });

        y = doc.lastAutoTable.finalY + 10;
    });

    // Open PDF
    window.open(doc.output('bloburl'), '_blank');
}

function generatePenaltyPaymentErrorPDF(errorData) {

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    let y = 15;

    doc.setFontSize(18);
    doc.text('Penalty Validation Errors', 14, y);
    y += 8;

    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, y);
    y += 12;

    // Expecting errorData.penalty_data
    if (!errorData.penalty_data || Object.keys(errorData.penalty_data).length === 0) {
        doc.text('No penalty errors found.', 14, y);
        doc.output('bloburl');
        window.open(doc.output('bloburl'), '_blank');
        return;
    }

    const tableBody = [];

    Object.entries(errorData.penalty_data).forEach(([key, message]) => {

        // slots.0.amount → slot 1, Amount
        const match = key.match(/slots\.(\d+)\.(.+)/);

        if (match) {
            const slotNo = parseInt(match[1], 10) + 1;
            const fieldName = match[2]
                .replace(/_/g, ' ')
                .replace(/([A-Z])/g, ' $1')
                .toUpperCase();

            tableBody.push([
                `Penalty Slot ${slotNo} – ${fieldName}`,
                message
            ]);
        }
    });

    doc.autoTable({
        startY: y,
        head: [['Penalty Field', 'Error Message']],
        body: tableBody,
        theme: 'grid',
        styles: { fontSize: 10 },
        headStyles: { fillColor: [220, 220, 220] }
    });

    doc.output('bloburl');
    window.open(doc.output('bloburl'), '_blank');
}

const handleSelectedPaymentCategoryStudents = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterPaymentCategoryStudents(id);
}

let selectedDays = [];

const dateChipBoxAction = (clickedElement) => {
    const chip = $(clickedElement);
    const day = chip.data('day-id');

    chip.toggleClass('selected');

    if (chip.hasClass('selected')) {
        selectedDays.push(day);
    } else {
        selectedDays = selectedDays.filter(d => d !== day);
    }

};

// function toggleSidebarSubmenu(button) {
//     const group = button.closest('.sidebar-group');
//     const submenu = group.querySelector('.sidebar-submenu');
//     const arrow = group.querySelector('.submenu-arrow');

//     submenu.classList.toggle('show');
//     arrow.classList.toggle('rotated');
// }

function toggleSidebarSubmenu(btn) {
    const submenu = btn.nextElementSibling;
    if (!submenu) return;

    const isOpen = submenu.classList.toggle('show');
    const arrow = btn.querySelector('.submenu-arrow');
    if (arrow) arrow.classList.toggle('rotated', isOpen);
}

const downloadLeadFormatCSV = () => {
    const filePath = 'assets/uploads/csv-format/leadFormat.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'lead-format.csv';
    link.click();
}

const openEditLeadModal = (lead) => {

    $("#edit-lead-btn").off("click");
    $("#edit-name-feedback").removeClass("invalid-feedback").html("");
    $("#edit-cnumber-feedback").removeClass("invalid-feedback").html("");
    $("#edit-wnumber-feedback").removeClass("invalid-feedback").html("");
    $("#edit-name-feedback").removeClass("invalid-feedback").html("");
    $("#edit-agent-feedback").removeClass("invalid-feedback").html("");

    $("#edit-name").val(lead.name || "");
    $("#edit-cnumber").val(lead.contact);
    $("#edit-wnumber").val(lead.whatsapp);
    $("#edit-aid").val(lead.uid || 0).trigger("change");
    $("#edit-programme").val(lead.programme_id || "");


    $("#edit-lead-btn").click(function () {
        editLead(this, lead.nid);
    });

    $("#editLead").modal("show");
}

const handleNewLeadsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterNewLeads();
}

const handleLeadsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterAllLeads();
}

const genaratePDF = (title, response) => {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();

    const pageWidth = pdf.internal.pageSize.getWidth();
    let y = 10;

    const img = new Image();
    img.src = "./assets/imgs/common/logo.png";

    img.onload = function () {

        const logoWidth = 35;
        const logoHeight = 25;
        const logoX = (pageWidth - logoWidth) / 2;

        pdf.addImage(img, "PNG", logoX, y, logoWidth, logoHeight);

        y += 35;

        pdf.setFont("helvetica", "bold");
        pdf.setFontSize(16);

        pdf.setTextColor(0, 38, 86);

        pdf.text("Study World Lanka Campus", pageWidth / 2, y, { align: "center" });

        y += 8;
        pdf.setTextColor(0, 0, 0);
        pdf.setFont("helvetica", "medium");
        pdf.setFontSize(16);
        pdf.text(title, pageWidth / 2, y, { align: "center" });

        y += 8;

        pdf.setFont("helvetica", "normal");
        pdf.setFontSize(10);

        const now = new Date();

        pdf.text(`Date: ${now.toLocaleDateString()}`, 14, y);
        pdf.text(`Time: ${now.toLocaleTimeString()}`, 14, y + 5);

        y += 12;

        pdf.setDrawColor(200);
        pdf.line(10, y, pageWidth - 10, y);

        y += 5;

        let tableData = [];

        response.dataErrors.forEach((rowErrors, i) => {
            rowErrors.forEach((error) => {
                tableData.push([i + 1, error]);
            });
        });

        pdf.autoTable({
            startY: y,
            head: [['Row', 'Error Message']],
            body: tableData,

            theme: 'grid',

            styles: {
                font: 'helvetica',
                fontSize: 10,
                cellPadding: 4
            },

            headStyles: {
                fillColor: [0, 38, 86],
                textColor: 255,
                fontStyle: 'bold'
            },

            alternateRowStyles: {
                fillColor: [245, 247, 250]
            },

            columnStyles: {
                0: { cellWidth: 20 },
                1: { cellWidth: 150 }
            },

            didDrawPage: function () {
                const pageHeight = pdf.internal.pageSize.getHeight();

                pdf.setFontSize(9);
                pdf.setTextColor(150);

                pdf.text(
                    `Page ${pdf.internal.getNumberOfPages()}`,
                    pageWidth / 2,
                    pageHeight - 10,
                    { align: "center" }
                );
            }
        });

        const pdfBlob = pdf.output('blob');
        const url = URL.createObjectURL(pdfBlob);
        window.open(url);

        setTimeout(() => URL.revokeObjectURL(url), 10000);
    }
}


const initializeCounsellorTargets = () => {
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getCurrentMonthRange() {
        const today = new Date();
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        return {
            start: formatDate(start),
            end: formatDate(end)
        };
    }

    function getCurrentWeekRange() {
        const today = new Date();
        const day = today.getDay(); // Sun=0, Mon=1, ...
        const diffToMonday = day === 0 ? -6 : 1 - day;

        const start = new Date(today);
        start.setDate(today.getDate() + diffToMonday);

        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return {
            start: formatDate(start),
            end: formatDate(end)
        };
    }

    function getIntakeRange(intakeStartDate) {
        const end = new Date(intakeStartDate);
        const start = new Date(intakeStartDate);
        start.setDate(start.getDate() - 30);

        return {
            start: formatDate(start),
            end: formatDate(end)
        };
    }

    function resetDates() {
        $("#start-date").val("");
        $("#end-date").val("");
    }

    function autoSetTargetDates() {
        const selectedPeriodText = $("#target-period option:selected").text().trim().toLowerCase();
        const intakeStart = $("#intake-id option:selected").data("start");

        if ($("#target-period").val() === "0") {
            resetDates();
            return;
        }

        if (selectedPeriodText.includes("month")) {
            const range = getCurrentMonthRange();
            $("#start-date").val(range.start);
            $("#end-date").val(range.end);
        } else if (selectedPeriodText.includes("week")) {
            const range = getCurrentWeekRange();
            $("#start-date").val(range.start);
            $("#end-date").val(range.end);
        } else if (selectedPeriodText.includes("intake")) {
            if (intakeStart) {
                const range = getIntakeRange(intakeStart);
                $("#start-date").val(range.start);
                $("#end-date").val(range.end);
            } else {
                resetDates();
            }
        } else {
            resetDates();
        }
    }

    $("#target-period").on("change", function () {
        autoSetTargetDates();
        if ($("#target-period").val() === "3") {
            $("#intake-container").hasClass("d-none") ? $("#intake-container").removeClass("d-none") : "";
        }

    });

    $("#intake-id").on("change", function () {
        const selectedPeriodText = $("#target-period option:selected").text().trim().toLowerCase();

        if (selectedPeriodText.includes("intake")) {
            autoSetTargetDates();
        }
    });

    $("#addTarget").on("shown.bs.modal", function () {
        autoSetTargetDates();
    });

}

const uploadVerificationImage = (inputId, imageId, index) => {

    const fileInput = document.getElementById(inputId);
    const imageTag = document.getElementById(imageId);

    fileInput.click();

    fileInput.onchange = (e) => {

        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();

        reader.onload = () => {

            imageTag.src = reader.result;

            // hide default image
            const defaultImg = document.getElementById(`default-job-img${index}`);
            if (defaultImg && !defaultImg.classList.contains('d-none')) {
                defaultImg.classList.add('d-none');
            }

            // show preview box
            const imageBox = document.getElementById(`image-box-${index}`);
            if (imageBox && imageBox.classList.contains('d-none')) {
                imageBox.classList.remove('d-none');
            }
        };

        reader.readAsDataURL(file);
    };
};


const getInstallmentPenalty = () => {
    const isEnabled = document.querySelector("#enable-panelty")?.checked;

    if (!isEnabled) return null;

    const option = document.querySelector("#panelty-option")?.value;

    // Flat / Percentage
    if (option === "2" || option === "3") {
        return {
            mode: option,
            type: document.querySelector("#flat-type")?.value,
            amount: document.querySelector("#flat-amount")?.value,
        };
    }

    // Slot-based penalties
    if (option === "4") {
        const slots = [];

        document.querySelectorAll(".custom-slot").forEach(slot => {
            slots.push({
                type: slot.querySelector(".panelty-type")?.value,
                amount: slot.querySelector(".panelty-amount")?.value,
                days: slot.querySelector(".panelty-days")?.value
            });
        });

        return {
            mode: option,
            slots
        };
    }

    return null;
};

function collectPaymentData() {

    const penaltyEnabled = document.querySelector("#enable-panelty")?.checked || false;
    const installmentId = document.querySelector("#enable-panelty")?.dataset.installmentId || null;

    const payment = {
        installmentId: installmentId,
        paymentType: document.querySelector("#payment-type")?.value || null,
        amount: document.querySelector("#payment-amount")?.value || null,
        discountType: document.querySelector("#discount-type")?.value || null,
        discount: document.querySelector("#discount")?.value || null,
        discountNote: document.querySelector("#discount-note")?.value || null,
        dueDate: document.querySelector("#due-date")?.value || null,
        penaltyEnabled: penaltyEnabled,
        note: document.querySelector("#payment-note")?.value || null,
        penalty: null
    };

    if (penaltyEnabled) {
        const option = document.querySelector("#panelty-option")?.value;

        // Flat / percentage penalty
        if (option === "2" || option === "3") {
            payment.penalty = {
                mode: option,
                type: document.querySelector("#flat-type")?.value || null,
                amount: document.querySelector("#flat-amount")?.value || null
            };
        }

        // Slot-based penalty
        if (option === "4") {
            payment.penalty = {
                mode: option,
                slots: []
            };

            document.querySelectorAll(".custom-slot").forEach(slot => {
                payment.penalty.slots.push({
                    type: slot.querySelector(".panelty-type")?.value || null,
                    amount: slot.querySelector(".panelty-amount")?.value || null,
                    days: slot.querySelector(".panelty-days")?.value || null
                });
            });
        }
    }

    return payment;
}

const handleDailyWorksPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterDailyWorksLeads();
}


function changeSource(value) {
    if (parseInt(value) === 13) {
        $("#institutes").removeClass("d-none");
    } else {
        $("#institutes").addClass("d-none");
        $("#institutes-branches").addClass("d-none");

        $("#institute-selector").val("0");
        $("#institute-branch-selector").val("0");

        $("#institute-branch-selector option").each(function (index) {
            if (index === 0) {
                $(this).prop("hidden", false);
            } else {
                $(this).prop("hidden", true);
            }
        });
    }
}

function changeInstitute(value) {
    $("#institute-branch-selector").val("0");

    if (parseInt(value) === 0) {
        $("#institutes-branches").addClass("d-none");

        $("#institute-branch-selector option").each(function (index) {
            if (index === 0) {
                $(this).prop("hidden", false);
            } else {
                $(this).prop("hidden", true);
            }
        });

        return;
    }

    const selectedOption = $("#institute-selector option:selected");
    const branchCount = parseInt(selectedOption.data("branch-count")) || 0;

    let hasVisibleBranches = false;

    $("#institute-branch-selector option").each(function (index) {
        if (index === 0) {
            $(this).prop("hidden", false);
            return;
        }

        const instituteId = parseInt($(this).data("institute"));

        if (instituteId === parseInt(value)) {
            $(this).prop("hidden", false);
            hasVisibleBranches = true;
        } else {
            $(this).prop("hidden", true);
        }
    });

    if (branchCount > 0 && hasVisibleBranches) {
        $("#institutes-branches").removeClass("d-none");
    } else {
        $("#institutes-branches").addClass("d-none");
    }
}

const initializeLeads = () => {
    $(".lead-source").each(function () {
        const id = parseInt($(this).data("id")) || 0;
        const color = getSoftColor(id);

        $(this).css({
            "background-color": color.bg,
            "color": color.text
        });
    });
    function getSoftColor(id) {
        const hue = (id * 137) % 360;

        return {
            bg: `hsl(${hue}, 60%, 85%)`,   // light background
            text: `hsl(${hue}, 70%, 35%)`  // darker text
        };
    }
}


const confirmDeleteIntake = (id) => {
    $("#confirmRemoveBatch").off("click");
    $("#confirmRemoveBatch").on("click", function () {
        removeIntake(id);
    });
    $("#removeBatchConfirmModal").modal("show");
}

const confirmDeletePrograme = (id) => {
    $("#confirmRemoveProgramme").off("click");
    $("#confirmRemoveProgramme").on("click", function () {
        removeProgramme(id);
    });
    $("#removeProgrammeConfirmModal").modal("show");
}

const confirmDeleteIntakeGroup = (id) => {
    $("#confirmRemoveIntake").off("click");
    $("#confirmRemoveIntake").on("click", function () {
        removeIntakeGroup(id);
    });
    $("#removeIntakeGroupConfirmModal").modal("show");
}

const confirmDeleteFaculty = (id) => {
    $("#confirmRemoveFaculty").off("click");
    $("#confirmRemoveFaculty").on("click", function () {
        removeFaculty(id);
    });
    $("#removeFacultyConfirmModal").modal("show");
}
function toggleLeadCardDetails(id, button) {
    const section = document.getElementById(id);

    if (section.classList.contains('show')) {
        section.classList.remove('show');
        button.classList.remove('active');
        button.querySelector('.btn-text').textContent = 'Read More';
    } else {
        section.classList.add('show');
        button.classList.add('active');
        button.querySelector('.btn-text').textContent = 'Read Less';
    }
}

function sendRegistrationLink(encodedId, enablePayment, amount) {

    const url = "https://portal.orinstolms.com/student-registration-application?id=" + encodedId;

    document.getElementById("registrationLinkInput").value = url;

    const checkbox = document.getElementById('regPaymentEnabled');
    const wrap = document.getElementById('regPaymentAmountWrap');
    const amtInput = document.getElementById('regPaymentAmount');
    const feedback = document.getElementById('regPaymentAmountFeedback');

    if (enablePayment) {
        checkbox.checked = true;
        wrap.classList.remove('d-none');
        wrap.classList.add('open');
        amtInput.value = amount || '';
    } else {
        checkbox.checked = false;
        wrap.classList.remove('open');
        amtInput.value = '';
        feedback.textContent = '';
    }

    const modal = new bootstrap.Modal(document.getElementById('registrationLinkModal'));
    modal.show();
}

function copyRegistrationLink() {
    const input = document.getElementById("registrationLinkInput");
    input.select();
    input.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(input.value);

    showToast('success', "Registration link copied to clipboard");
}

const handleMarketingPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterMarketingPermissions();
}

let ongoingLeadMobiles = [];

const initializeAddLead = () => {
    fetch("api/getOngoingLeadMobiles.php")
        .then(response => response.json())
        .then(data => {
            if (data.done) {
                ongoingLeadMobiles = data.mobiles;
            }
        })
        .catch(error => {
            console.error("Mobile loading error:", error);
        });

    $("#mobile").on("keyup", function () {
        console.log($(this).val());
        let mobile = $(this).val().trim();

        $("#mobile-feedback").text("");
        let normalizedMobile = normalizePhoneNumber($("#mobile").val());

        if (normalizedMobile && ongoingLeadMobiles.includes(normalizedMobile)) {
            $("#duplicateMobileModal").modal("show");
        }
    });
};

const normalizePhoneNumber = (number, defaultCountryCode = "94") => {
    if (!number) return false;

    let mobile = number.trim().replace(/[^\d+]/g, "");

    if (/^\+[1-9][0-9]{7,14}$/.test(mobile)) {
        return mobile;
    }

    if (defaultCountryCode === "94" && /^0(7[0-9]{8})$/.test(mobile)) {
        return "+94" + mobile.match(/^0(7[0-9]{8})$/)[1];
    }

    if (defaultCountryCode === "94" && /^(7[0-9]{8})$/.test(mobile)) {
        return "+94" + mobile;
    }

    if (defaultCountryCode === "94" && /^94(7[0-9]{8})$/.test(mobile)) {
        return "+94" + mobile.match(/^94(7[0-9]{8})$/)[1];
    }

    if (/^[1-9][0-9]{7,14}$/.test(mobile)) {
        return "+" + mobile;
    }

    return false;
};

const handleLeaveRequestPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterLeaveRequests();
}

const handleMyLeaveRequestPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterMyLeaveRequests();
}

function searchUser(keyword) {
    const box = document.getElementById("userSuggestions");

    if (keyword.length < 1) {
        box.innerHTML = "";
        return;
    }

    const filtered = users.filter(u =>
        u.name.toLowerCase().includes(keyword.toLowerCase())
    );

    let html = "";

    filtered.slice(0, 8).forEach(user => {
        html += `
            <div class="list-group-item list-group-item-action d-flex align-items-center"
                 onclick="selectLeaveAnalysisUser('${user.id}', '${user.name}')"
                 style="cursor:pointer;">

                <img src="${user.img}" 
                     class="rounded-circle me-2" 
                     width="35" height="35"
                     onerror="this.src='assets/imgs/users/default-user.webp'">

                <span>${user.name}</span>
            </div>
        `;
    });

    box.innerHTML = html;
}

const enableSaveChangesWithProgramme = (val) => {
    const saveBtn = document.getElementById("saveProgrammeBtn");
    if (val !== 0) {
        saveBtn.removeAttribute("disabled");
    } else {
        saveBtn.setAttribute("disabled", true);
    }

}

const clearMobileField = () => {
    $("#mobile").val('');
}

const enableIntakeSelector = (val) => {
    console.log(val);
    if (val == 4) {
        $("#intake-container").removeClass('d-none');
    } else {
        $("#intake-container").hasClass('d-none') ? '' : $("#intake-container").addClass('d-none');;
    }
}

const REQUIRED_STUDENT_DOCUMENTS = [
    "birth_certificate",
    "nic_passport",
    "ol_result_sheet",
    "certificates",
    "grama_niladhari",
    "guardian_nic",
    "nic_size_photos",
    "student_photo",
    "academic_documents",

];

const studentDocumentFiles = {};


function initializeStudentDocumentUploads() {
    document.querySelectorAll(".student-doc-upload-box").forEach((box) => {
        const docKey = box.dataset.docKey;
        const dropArea = box.querySelector(".doc-drop-area");
        const input = box.querySelector(".student-doc-input");
        const browseBtn = box.querySelector(".doc-browse-btn");

        studentDocumentFiles[docKey] = [];

        browseBtn.addEventListener("click", function (e) {
            e.preventDefault();
            input.click();
        });

        dropArea.addEventListener("click", function (e) {
            if (!e.target.classList.contains("doc-browse-btn")) {
                input.click();
            }
        });

        input.addEventListener("change", function () {
            handleStudentDocumentFiles(box, input.files);
        });

        dropArea.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropArea.classList.add("drag-over-effect");
        });

        dropArea.addEventListener("dragleave", function () {
            dropArea.classList.remove("drag-over-effect");
        });

        dropArea.addEventListener("drop", function (e) {
            e.preventDefault();
            dropArea.classList.remove("drag-over-effect");
            handleStudentDocumentFiles(box, e.dataTransfer.files);
        });
    });
}

function handleStudentDocumentFiles(box, fileList) {
    const docKey = box.dataset.docKey;
    const input = box.querySelector(".student-doc-input");
    const feedback = box.querySelector(".doc-feedback");
    const allowMultiple = input.hasAttribute("multiple");

    feedback.innerHTML = "";

    const files = Array.from(fileList);

    if (files.length === 0) {
        return;
    }

    let validFiles = [];

    files.forEach((file) => {
        const validation = validateStudentDocumentFile(file, input);

        if (!validation.valid) {
            feedback.innerHTML = validation.message;
            return;
        }

        validFiles.push(file);
    });

    if (validFiles.length === 0) {
        return;
    }

    if (allowMultiple) {
        studentDocumentFiles[docKey] = [
            ...studentDocumentFiles[docKey],
            ...validFiles
        ];
    } else {
        studentDocumentFiles[docKey] = [validFiles[0]];
    }

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function validateStudentDocumentFile(file, input) {
    const maxFileSize = 8 * 1024 * 1024; // 8MB

    const allowedExtensions = input
        .getAttribute("accept")
        .split(",")
        .map((ext) => ext.trim().replace(".", "").toLowerCase());

    const fileExtension = file.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: `Invalid file type. Allowed: ${allowedExtensions.join(", ")}`
        };
    }

    if (file.size > maxFileSize) {
        return {
            valid: false,
            message: "File size must be less than 8MB"
        };
    }

    return {
        valid: true,
        message: ""
    };
}

function renderStudentDocumentPreview(box) {
    const docKey = box.dataset.docKey;
    const preview = box.querySelector(".doc-preview");

    preview.innerHTML = "";

    studentDocumentFiles[docKey].forEach((file, index) => {
        const template = `
            <div class="student-doc-file-card mb-2">
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <img src="assets/imgs/icons/${getStudentDocumentIcon(file)}" width="32" alt="file">
                    </div>

                    <div class="flex-grow-1">
                        <div class="student-doc-file-name">${escapeHtml(file.name)}</div>
                        <div class="student-doc-file-meta">
                            ${getReadableFileType(file.name)} • ${formatStudentDocumentFileSize(file.size)}
                        </div>
                    </div>

                    <div>
                        <iconify-icon 
                            icon="mdi:close-circle-outline"
                            width="22"
                            height="22"
                            class="remove-student-doc"
                            data-doc-key="${docKey}"
                            data-index="${index}">
                        </iconify-icon>
                    </div>
                </div>
            </div>
        `;

        preview.insertAdjacentHTML("beforeend", template);
    });

    preview.querySelectorAll(".remove-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeStudentDocumentFile(
                this.dataset.docKey,
                parseInt(this.dataset.index)
            );
        });
    });
}

function removeStudentDocumentFile(docKey, index) {
    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    const input = box.querySelector(".student-doc-input");

    studentDocumentFiles[docKey].splice(index, 1);

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function syncStudentDocumentInput(input, files) {
    const dataTransfer = new DataTransfer();

    files.forEach((file) => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

function validateRequiredStudentDocuments() {
    let isValid = true;

    REQUIRED_STUDENT_DOCUMENTS.forEach((docKey) => {
        const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
        const feedback = box.querySelector(".doc-feedback");

        feedback.innerHTML = "";

        if (!studentDocumentFiles[docKey] || studentDocumentFiles[docKey].length === 0) {
            feedback.innerHTML = "This document is required";
            isValid = false;
        }
    });

    return isValid;
}

function appendStudentDocumentsToFormData(formData) {
    Object.keys(studentDocumentFiles).forEach((docKey) => {
        studentDocumentFiles[docKey].forEach((file, index) => {
            formData.append(`${docKey}[]`, file);
        });
    });

    return formData;
}

function getStudentDocumentsData() {
    return studentDocumentFiles;
}

function getStudentDocumentIcon(file) {
    const extension = file.name.split(".").pop().toLowerCase();

    const iconMap = {
        pdf: "pdf.png",
        doc: "doc.png",
        docx: "doc.png",
        jpg: "image.png",
        jpeg: "image.png",
        png: "image.png"
    };

    return iconMap[extension] || "file.png";
}

function getReadableFileType(fileName) {
    return fileName.split(".").pop().toUpperCase();
}

function formatStudentDocumentFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + " bytes";
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2) + " KB";
    }

    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.innerText = text;
    return div.innerHTML;
}


const EXISTING_STUDENT_DOCUMENTS = window.EXISTING_STUDENT_DOCUMENTS || {};

const removedExistingDocuments = {};

function initializeUpdateStudentDocumentUploads() {
    document.querySelectorAll(".student-doc-upload-box").forEach((box) => {
        const docKey = box.dataset.docKey;
        const dropArea = box.querySelector(".doc-drop-area");
        const input = box.querySelector(".student-doc-input");
        const browseBtn = box.querySelector(".doc-browse-btn");

        studentDocumentFiles[docKey] = [];
        removedExistingDocuments[docKey] = [];

        browseBtn.addEventListener("click", function (e) {
            e.preventDefault();
            input.click();
        });

        dropArea.addEventListener("click", function (e) {
            if (!e.target.classList.contains("doc-browse-btn")) {
                input.click();
            }
        });

        input.addEventListener("change", function () {
            handleStudentDocumentFiles(box, input.files);
        });

        dropArea.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropArea.classList.add("drag-over-effect");
        });

        dropArea.addEventListener("dragleave", function () {
            dropArea.classList.remove("drag-over-effect");
        });

        dropArea.addEventListener("drop", function (e) {
            e.preventDefault();
            dropArea.classList.remove("drag-over-effect");
            handleStudentDocumentFiles(box, e.dataTransfer.files);
        });

        renderStudentDocumentPreview(box);
    });
}

function getExistingDocs(docKey) {
    return (EXISTING_STUDENT_DOCUMENTS[docKey] || []).filter((doc) => {
        return !removedExistingDocuments[docKey].includes(String(doc.id));
    });
}

function handleStudentDocumentFiles(box, fileList) {
    const docKey = box.dataset.docKey;
    const input = box.querySelector(".student-doc-input");
    const feedback = box.querySelector(".doc-feedback");
    const allowMultiple = input.hasAttribute("multiple");

    feedback.innerHTML = "";

    const files = Array.from(fileList);

    if (files.length === 0) {
        return;
    }

    let validFiles = [];

    files.forEach((file) => {
        const validation = validateStudentDocumentFile(file, input);

        if (!validation.valid) {
            feedback.innerHTML = validation.message;
            return;
        }

        validFiles.push(file);
    });

    if (validFiles.length === 0) {
        return;
    }

    if (allowMultiple) {
        studentDocumentFiles[docKey] = [
            ...studentDocumentFiles[docKey],
            ...validFiles
        ];
    } else {
        studentDocumentFiles[docKey] = [validFiles[0]];
    }

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function validateStudentDocumentFile(file, input) {
    const maxFileSize = 8 * 1024 * 1024;

    const allowedExtensions = input
        .getAttribute("accept")
        .split(",")
        .map((ext) => ext.trim().replace(".", "").toLowerCase());

    const fileExtension = file.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: `Invalid file type. Allowed: ${allowedExtensions.join(", ")}`
        };
    }

    if (file.size > maxFileSize) {
        return {
            valid: false,
            message: "File size must be less than 8MB"
        };
    }

    return {
        valid: true,
        message: ""
    };
}

function renderStudentDocumentPreview(box) {
    const docKey = box.dataset.docKey;
    const preview = box.querySelector(".doc-preview");

    preview.innerHTML = "";

    const existingDocs = getExistingDocs(docKey);

    existingDocs.forEach((doc) => {
        const template = `
    <div class="student-doc-file-card mb-2 existing-doc-card cursor-pointer"
        onclick="window.open('${escapeHtml(doc.url)}', '_blank')">

        <div class="d-flex align-items-center">
            
            <div class="me-2">
                <img src="assets/imgs/icons/${getStudentDocumentIconByName(doc.name)}" width="32" alt="file">
            </div>

            <div class="flex-grow-1 overflow-hidden">
                <div class="student-doc-file-name text-truncate">
                    ${escapeHtml(doc.name)}
                </div>

                <div class="student-doc-file-meta">
                    Existing Document • ${escapeHtml(doc.date_added || '')}
                </div>
            </div>

            ${escapeHtml(doc.access_remove) == true ? `
            <div class="ms-2">
                <iconify-icon 
                    icon="mdi:close-circle-outline"
                    width="22"
                    height="22"
                    class="remove-existing-student-doc cursor-pointer text-danger"
                    data-doc-key="${docKey}"
                    data-doc-id="${doc.id}"
                    onclick="event.stopPropagation();">
                </iconify-icon>
            </div>
                ` : ``}
         
        </div>
    </div>
`;

        preview.insertAdjacentHTML("beforeend", template);
    });

    studentDocumentFiles[docKey].forEach((file, index) => {
        const template = `
            <div class="student-doc-file-card mb-2">
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <img src="assets/imgs/icons/${getStudentDocumentIcon(file)}" width="32" alt="file">
                    </div>

                    <div class="flex-grow-1">
                        <div class="student-doc-file-name">${escapeHtml(file.name)}</div>
                        <div class="student-doc-file-meta">
                            New File • ${getReadableFileType(file.name)} • ${formatStudentDocumentFileSize(file.size)}
                        </div>
                    </div>

                    <div>
                        <iconify-icon 
                            icon="mdi:close-circle-outline"
                            width="22"
                            height="22"
                            class="remove-student-doc cursor-pointer"
                            data-doc-key="${docKey}"
                            data-index="${index}">
                        </iconify-icon>
                    </div>
                </div>
            </div>
        `;

        preview.insertAdjacentHTML("beforeend", template);
    });

    preview.querySelectorAll(".remove-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeStudentDocumentFile(
                this.dataset.docKey,
                parseInt(this.dataset.index)
            );
        });
    });

    preview.querySelectorAll(".remove-existing-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeExistingStudentDocument(
                this.dataset.docKey,
                this.dataset.docId
            );
        });
    });
}

function removeExistingStudentDocument(docKey, docId) {
    removedExistingDocuments[docKey].push(String(docId));

    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    renderStudentDocumentPreview(box);
}

function removeStudentDocumentFile(docKey, index) {
    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    const input = box.querySelector(".student-doc-input");

    studentDocumentFiles[docKey].splice(index, 1);

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function syncStudentDocumentInput(input, files) {
    const dataTransfer = new DataTransfer();

    files.forEach((file) => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

function validateRequiredStudentDocuments() {
    let isValid = true;

    REQUIRED_STUDENT_DOCUMENTS.forEach((docKey) => {
        const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
        const feedback = box.querySelector(".doc-feedback");

        feedback.innerHTML = "";

        const hasExisting = getExistingDocs(docKey).length > 0;
        const hasNew = studentDocumentFiles[docKey] && studentDocumentFiles[docKey].length > 0;

        if (!hasExisting && !hasNew) {
            feedback.innerHTML = "This document is required";
            isValid = false;
        }
    });

    return isValid;
}

function appendStudentDocumentsToFormData(formData) {
    Object.keys(studentDocumentFiles).forEach((docKey) => {
        studentDocumentFiles[docKey].forEach((file) => {
            formData.append(`${docKey}[]`, file);
        });
    });

    Object.keys(removedExistingDocuments).forEach((docKey) => {
        removedExistingDocuments[docKey].forEach((docId) => {
            formData.append(`removed_documents[]`, docId);
        });
    });

    return formData;
}

function getStudentDocumentIcon(file) {
    return getStudentDocumentIconByName(file.name);
}

function getStudentDocumentIconByName(fileName) {
    const extension = fileName.split(".").pop().toLowerCase();

    const iconMap = {
        pdf: "pdf.png",
        doc: "doc.png",
        docx: "doc.png",
        jpg: "image.png",
        jpeg: "image.png",
        png: "image.png"
    };

    return iconMap[extension] || "file.png";
}

function getReadableFileType(fileName) {
    return fileName.split(".").pop().toUpperCase();
}

function formatStudentDocumentFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + " bytes";
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2) + " KB";
    }

    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.innerText = text ?? "";
    return div.innerHTML;
}


const openEditTargetModal = (targetId, type, period, intake, start, end, value) => {
    $("#edit-target-id").val(targetId);
    $("#edit-target-type").val(type);
    $("#edit-target-period").val(period);
    $("#edit-intake-id").val(intake || 0);
    $("#edit-start-date").val(start);
    $("#edit-end-date").val(end);
    $("#edit-target-value").val(value);

    toggleEditIntakeContainer();

    const modal = new bootstrap.Modal(document.getElementById("editTarget"));
    modal.show();
};

const toggleEditIntakeContainer = () => {
    const period = $("#edit-target-period").val();

    if (period == 3) {
        $("#edit-intake-container").removeClass("d-none");
    } else {
        $("#edit-intake-container").addClass("d-none");
        $("#edit-intake-id").val(0);
    }
};

const handleLeadsTargetPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLeadsTarget(id);
}


const handleStudentsPaymentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterStudentsPayments();
}


const handleAwaitingPaymentPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage2 = pageId;
            break;
        case 'previousPage':
            if (selectedPage2 > 1) {
                selectedPage2--;
            }
            break;
        case 'nextPage':
            if (selectedPage2 < totalPages) {
                selectedPage2++;
            }
            break;
        case 'filter':
            selectedPage2 = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = 0;
            sortCondition1 = (sortCondition1 === 1 ? 2 : 1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = 0;
            sortCondition2 = (sortCondition2 == 1 ? 2 : 1);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition4 = 0;
            sortCondition3 = (sortCondition3 === 1 ? 2 : 1);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition3 = 0;
            sortCondition4 = (sortCondition4 === 1 ? 2 : 1);
            break;

    }

    filterAwaitingPayments();
}


const handleCounsilerPerformancePagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterCounsilerPerformance(id);
}

const lecturerContractValidationMap = {
    contract_title: ["#contract-title", "#contract-title-feedback"],
    contract_start_date: ["#contract-start-date", "#contract-start-date-feedback"],
    contract_end_date: ["#contract-end-date", "#contract-end-date-feedback"],
    payment_cycle: ["#payment-cycle", "#payment-cycle-feedback"]
};

function clearLecturerContractErrors() {
    Object.values(lecturerContractValidationMap).forEach(([input, feedback]) => {
        document.querySelector(input)?.classList.remove("is-invalid");
        const fb = document.querySelector(feedback);
        if (fb) fb.innerText = "";
    });

    document.querySelectorAll(".lecturer-contract-subject-card").forEach(card => {
        card.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
        card.querySelectorAll(".text-dark-red").forEach(el => el.innerText = "");
    });
}

function collectLecturerContractSubjects() {
    const subjects = [];

    document.querySelectorAll(".lecturer-contract-subject-card").forEach((card, index) => {
        subjects.push({
            index: index + 1,
            faculty_id: card.querySelector(".faculty-select")?.value || 0,
            programme_id: card.querySelector(".programme-select")?.value || 0,
            intake_id: card.querySelector(".intake-select")?.value || 0,
            level_id: card.querySelector(".level-select")?.value || 0,
            subject_id: card.querySelector(".subject-select")?.value || 0,
            teaching_mode: card.querySelector(".teaching-mode-select")?.value || "",
            lecture_payment_type: card.querySelector(".lecture-payment-type")?.value || 0,
            lecture_rate: card.querySelector(".lecture-rate")?.value || 0,
            minimum_payable_hours: card.querySelector(".minimum-payable-hours")?.value || 0,
            per_assignment_fee: card.querySelector(".assignment-rate")?.value || 0,
            per_examination_fee: card.querySelector(".exam-rate")?.value || 0,
            payment_note: card.querySelector(".subject-note")?.value.trim() || ""
        });
    });

    return subjects;
}

function showSubjectError(subjectIndex, fieldClass, feedbackClass, message) {
    const card = document.querySelectorAll(".lecturer-contract-subject-card")[subjectIndex - 1];
    if (!card) return;

    const input = card.querySelector(fieldClass);
    const feedback = card.querySelector(feedbackClass);

    if (input) input.classList.add("is-invalid");
    if (feedback) feedback.innerText = message;

    const collapse = card.querySelector(".accordion-collapse");
    if (collapse) {
        bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
    }
}

let lecturerContractSubjectIndex = 1;

function addLecturerContractSubject() {
    const accordion = document.getElementById("lecturerContractSubjectAccordion");
    const firstCard = accordion.querySelector(".lecturer-contract-subject-card");

    lecturerContractSubjectIndex++;

    const newIndex = lecturerContractSubjectIndex;
    const newCard = firstCard.cloneNode(true);

    newCard.dataset.subjectIndex = newIndex;

    const heading = newCard.querySelector(".accordion-header");
    const toggleBtn = newCard.querySelector(".accordion-button");
    const collapse = newCard.querySelector(".accordion-collapse");

    heading.id = `subject-heading-${newIndex}`;
    collapse.id = `subject-collapse-${newIndex}`;

    toggleBtn.setAttribute("data-bs-target", `#subject-collapse-${newIndex}`);
    toggleBtn.setAttribute("aria-controls", `subject-collapse-${newIndex}`);
    toggleBtn.setAttribute("aria-expanded", "true");

    collapse.setAttribute("aria-labelledby", `subject-heading-${newIndex}`);
    collapse.setAttribute("data-bs-parent", "#lecturerContractSubjectAccordion");

    newCard.querySelector(".subject-card-title").innerText = `Subject ${newIndex}`;
    newCard.querySelector(".subject-card-summary").innerText = "No subject selected yet";

    clearSubjectCardInputs(newCard);

    newCard.querySelectorAll(".remove-subject-btn").forEach(btn => {
        btn.classList.remove("d-none");
        btn.onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeLecturerContractSubject(this);
        };
    });

    accordion.querySelectorAll(".accordion-collapse.show").forEach(item => {
        bootstrap.Collapse.getOrCreateInstance(item).hide();
    });

    collapse.classList.remove("show");
    accordion.appendChild(newCard);

    bootstrap.Collapse.getOrCreateInstance(collapse, {
        toggle: false
    }).show();

    updateSubjectNumbers();
}

function clearSubjectCardInputs(card) {
    card.querySelectorAll("input").forEach(input => {
        input.value = "";
        input.classList.remove("is-invalid");
    });

    card.querySelectorAll("textarea").forEach(textarea => {
        textarea.value = "";
        textarea.classList.remove("is-invalid");
    });

    card.querySelectorAll("select").forEach(select => {
        select.value = "0";
        select.classList.remove("is-invalid");
    });

    card.querySelectorAll(".text-dark-red").forEach(feedback => {
        feedback.innerHTML = "";
    });

    card.querySelector(".programme-select").innerHTML = `<option value="0">- Select Programme -</option>`;
    card.querySelector(".intake-select").innerHTML = `<option value="0">- Select Intake -</option>`;
    card.querySelector(".level-select").innerHTML = `<option value="0">- Select Level -</option>`;
    card.querySelector(".subject-select").innerHTML = `<option value="0">- Select Subject -</option>`;
}

function removeLecturerContractSubject(btn) {
    const cards = document.querySelectorAll(".lecturer-contract-subject-card");

    if (cards.length <= 1) {
        showToast("error", "At least one subject is required");
        return;
    }

    const card = btn.closest(".lecturer-contract-subject-card");
    card.remove();

    updateSubjectNumbers();
}

function updateSubjectNumbers() {
    const cards = document.querySelectorAll(".lecturer-contract-subject-card");

    cards.forEach((card, index) => {
        const subjectNo = index + 1;

        card.dataset.subjectIndex = subjectNo;

        card.querySelector(".subject-card-title").innerText = `Subject ${subjectNo}`;

        card.querySelectorAll(".remove-subject-btn").forEach(btn => {
            if (index === 0) {
                btn.classList.add("d-none");
            } else {
                btn.classList.remove("d-none");
            }
        });
    });
}

function updateSubjectCardSummary(element) {
    const card = element.closest(".lecturer-contract-subject-card");

    const subjectSelect = card.querySelector(".subject-select");
    const teachingModeSelect = card.querySelector(".teaching-mode-select");

    const subjectText = subjectSelect && subjectSelect.value !== "0" ?
        subjectSelect.options[subjectSelect.selectedIndex].text :
        "No subject selected";

    const modeText = teachingModeSelect && teachingModeSelect.value !== "0" ?
        teachingModeSelect.options[teachingModeSelect.selectedIndex].text :
        "Teaching mode not selected";

    card.querySelector(".subject-card-summary").innerText =
        `${subjectText} • ${modeText}`;
}

let payrollComponentIndex = 1;

function addPayrollComponent() {
    const accordion = document.getElementById("payrollComponentAccordion");
    const firstCard = accordion.querySelector(".payroll-component-card");

    payrollComponentIndex++;

    const newIndex = payrollComponentIndex;
    const newCard = firstCard.cloneNode(true);

    newCard.dataset.componentIndex = newIndex;

    const heading = newCard.querySelector(".accordion-header");
    const toggleBtn = newCard.querySelector(".accordion-button");
    const collapse = newCard.querySelector(".accordion-collapse");

    heading.id = `payroll-component-heading-${newIndex}`;
    collapse.id = `payroll-component-collapse-${newIndex}`;

    toggleBtn.setAttribute("data-bs-target", `#payroll-component-collapse-${newIndex}`);
    toggleBtn.setAttribute("aria-controls", `payroll-component-collapse-${newIndex}`);
    toggleBtn.setAttribute("aria-expanded", "true");

    collapse.setAttribute("aria-labelledby", `payroll-component-heading-${newIndex}`);
    collapse.setAttribute("data-bs-parent", "#payrollComponentAccordion");

    newCard.querySelector(".component-card-title").innerText = `Component ${newIndex}`;
    newCard.querySelector(".component-card-summary").innerText = "No component configured yet";

    clearPayrollComponentInputs(newCard);

    accordion.querySelectorAll(".accordion-collapse.show").forEach(item => {
        bootstrap.Collapse.getOrCreateInstance(item).hide();
    });

    collapse.classList.remove("show");
    accordion.appendChild(newCard);

    bootstrap.Collapse.getOrCreateInstance(collapse, {
        toggle: false
    }).show();

    updatePayrollComponentNumbers();
}

function clearPayrollComponentInputs(card) {
    card.querySelectorAll("input").forEach(input => {
        input.value = "";
        input.classList.remove("is-invalid");
    });

    card.querySelectorAll("select").forEach(select => {
        select.value = "0";
        select.classList.remove("is-invalid");
    });

    card.querySelector(".component-epf").value = "0";
    card.querySelector(".component-taxable").value = "0";
    card.querySelector(".component-recurring").value = "1";
    card.querySelector(".component-deduction-base").value = "GROSS";

    card.querySelectorAll(".text-dark-red").forEach(feedback => {
        feedback.innerText = "";
    });
}

function removePayrollComponent(btn) {
    const cards = document.querySelectorAll(".payroll-component-card");

    if (cards.length <= 1) {
        showToast("error", "At least one payroll component is required");
        return;
    }

    btn.closest(".payroll-component-card").remove();
    updatePayrollComponentNumbers();
}

function updatePayrollComponentNumbers() {
    const cards = document.querySelectorAll(".payroll-component-card");

    cards.forEach((card, index) => {
        const no = index + 1;

        card.dataset.componentIndex = no;
        card.querySelector(".component-card-title").innerText = `Component ${no}`;

        const removeBtn = card.querySelector(".remove-payroll-component-btn");

        if (index === 0) {
            removeBtn.classList.add("d-none");
        } else {
            removeBtn.classList.remove("d-none");
        }
    });
}

function updatePayrollComponentSummary(element) {
    const card = element.closest(".payroll-component-card");

    const nameSelect = card.querySelector(".component-name-select");
    const name = nameSelect.options[nameSelect.selectedIndex]?.getAttribute("data-component-name")?.trim() || "Unnamed Component";
    const type = card.querySelector(".component-type").value;
    const amount = card.querySelector(".component-amount").value || "0";

    const typeText = type !== "0" ? type : "Type not selected";

    card.querySelector(".component-card-summary").innerText =
        `${name} • ${typeText} • ${amount}`;

    // Show/hide deduction base field based on component type
    const deductionBaseContainer = card.querySelector(".component-deduction-base-container");
    if (deductionBaseContainer) {
        if (type === "DEDUCTION") {
            deductionBaseContainer.style.display = "";
        } else {
            deductionBaseContainer.style.display = "none";
        }
    }
}

const payrollProfileValidationMap = {
    user_id: ["#employee", "#employee-feedback"],
    employment_type: ["#employment-type", "#employment-type-feedback"],
    epf_no: ["#epf-no", "#epf-no-feedback"],
    basic_salary: ["#basic-salary", "#basic-salary-feedback"],
    epf_employee_rate: ["#epf-employee-rate", "#epf-employee-rate-feedback"],
    epf_employer_rate: ["#epf-employer-rate", "#epf-employer-rate-feedback"],
    etf_rate: ["#etf-rate", "#etf-rate-feedback"],
    bank_name: ["#bank-name", "#bank-name-feedback"],
    bank_account_no: ["#bank-account-no", "#bank-account-no-feedback"],
    bank_branch: ["#bank-branch", "#bank-branch-feedback"],
};

function clearPayrollProfileErrors() {
    Object.values(payrollProfileValidationMap).forEach(([inputSelector, feedbackSelector]) => {
        const input = document.querySelector(inputSelector);
        const feedback = document.querySelector(feedbackSelector);

        if (input) input.classList.remove("is-invalid");
        if (feedback) feedback.innerText = "";
    });

    document.querySelectorAll(".payroll-component-card").forEach(card => {
        card.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
        card.querySelectorAll(".text-dark-red").forEach(el => el.innerText = "");
    });
}

function collectPayrollComponents() {
    const components = [];

    document.querySelectorAll(".payroll-component-card").forEach((card, index) => {
        components.push({
            index: index + 1,
            component_id: card.querySelector(".component-id")?.value || 0,
            component_name: card.querySelector(".component-name-select")?.value.trim() || "",
            component_type: card.querySelector(".component-type")?.value || "0",
            amount: card.querySelector(".component-amount")?.value || 0,
            is_epf_applicable: card.querySelector(".component-epf")?.value || 0,
            is_taxable: card.querySelector(".component-taxable")?.value || 0,
            is_recurring: card.querySelector(".component-recurring")?.value || 1,
            deduction_base: card.querySelector(".component-deduction-base")?.value || "GROSS"
        });
    });

    return components;
}

function showPayrollComponentError(index, inputClass, feedbackClass, message) {
    const card = document.querySelectorAll(".payroll-component-card")[index - 1];

    if (!card) return;

    const input = card.querySelector(inputClass);
    const feedback = card.querySelector(feedbackClass);

    if (input) input.classList.add("is-invalid");
    if (feedback) feedback.innerText = message;

    const collapse = card.querySelector(".accordion-collapse");

    if (collapse) {
        bootstrap.Collapse.getOrCreateInstance(collapse, {
            toggle: false
        }).show();
    }
}

let payrollEmployeeComponentIndex = 0;
const initializePayrollEmployeeDetails = (id) => {
    payrollEmployeeComponentIndex = document.querySelectorAll(".payroll-employee-component-card").length || 0;
}

const payrollEmployeeValidationMap = {
    basic_salary: ["#basic-salary", "#basic-salary-feedback"]
};

function clearPayrollEmployeeErrors() {
    document.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
    document.querySelectorAll(".text-dark-red").forEach(el => el.innerText = "");
}

function addPayrollEmployeeComponent() {
    const accordion = document.getElementById("payrollEmployeeComponentAccordion");

    payrollEmployeeComponentIndex++;
    const index = payrollEmployeeComponentIndex;

    // Build options from the pre-loaded JSON
    const componentOptions = PAYROLL_COMPONENTS.map(comp =>
        `<option value="${comp.payroll_component_id}" data-component-name="${comp.payroll_component}">
            ${comp.payroll_component}
        </option>`
    ).join('');

    const item = document.createElement("div");
    item.className = "accordion-item br-0 payroll-employee-component-card";
    item.dataset.componentIndex = index;
    item.dataset.componentId = "0";

    item.innerHTML = `
        <h2 class="accordion-header payroll-component-header" id="payroll-employee-component-heading-${index}">
            <button class="accordion-button br-0 payroll-component-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#payroll-employee-component-collapse-${index}"
                aria-expanded="true"
                aria-controls="payroll-employee-component-collapse-${index}">
                <div class="component-header-content">
                    <span class="text-14 fw-semibold main-text component-card-title">
                        Component ${index}
                    </span>
                    <span class="text-12 sub-text component-card-summary">
                        New component • Not configured
                    </span>
                </div>
            </button>
            <button type="button"
                class="subject-header-delete-btn remove-payroll-employee-component-btn"
                onclick="removePayrollEmployeeComponent(this)"
                title="Remove Component">
                <iconify-icon icon="solar:trash-bin-trash-bold" width="16" height="16"></iconify-icon>
            </button>
        </h2>

        <div id="payroll-employee-component-collapse-${index}"
            class="accordion-collapse collapse"
            aria-labelledby="payroll-employee-component-heading-${index}"
            data-bs-parent="#payrollEmployeeComponentAccordion">

            <div class="accordion-body component-theme">
                <div class="row gx-3 gy-3">

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text required">Component Name</label>
                        <select class="form-select custom-select mt-2 component-name-select"
                            onchange="updatePayrollEmployeeComponentSummary(this)">
                            <option value="" selected>- Select -</option>
                            ${componentOptions}
                        </select>
                        <span class="component-name-feedback text-dark-red text-12"></span>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text required">Component Type</label>
                        <select class="form-select custom-select mt-2 component-type"
                            onchange="updatePayrollEmployeeComponentSummary(this); toggleDeductionBase(this); calculatePayrollPreview();">
                            <option value="0">- Select -</option>
                            <option value="EARNING">Earning</option>
                            <option value="DEDUCTION">Deduction</option>
                        </select>
                        <span class="component-type-feedback text-dark-red text-12"></span>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text required">Amount</label>
                        <input type="number"
                            class="form-control custom-input mt-2 component-amount"
                            min="0"
                            oninput="updatePayrollEmployeeComponentSummary(this); calculatePayrollPreview();">
                        <span class="component-amount-feedback text-dark-red text-12"></span>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text">EPF Applicable</label>
                        <select class="form-select custom-select mt-2 component-epf"
                            onchange="calculatePayrollPreview()">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text">Taxable</label>
                        <select class="form-select custom-select mt-2 component-taxable">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column payroll-employee-deduction-base-container" style="display: none;">
                        <label class="text-14 main-text">Calculate On</label>
                        <select class="form-select custom-select mt-2 component-employee-deduction-base">
                            <option value="GROSS">Gross Salary</option>
                            <option value="NET">Net Salary</option>
                        </select>
                        <p class="text-12 sub-text mt-1 mb-0">Applies only to deductions</p>
                    </div>

                    <div class="col-12 col-md-4 d-flex flex-column">
                        <label class="text-14 main-text">Note</label>
                        <input type="text" class="form-control custom-input mt-2 component-note">
                    </div>

                </div>
            </div>
        </div>
    `;

    accordion.querySelectorAll(".accordion-collapse.show").forEach(collapse => {
        bootstrap.Collapse.getOrCreateInstance(collapse).hide();
    });

    accordion.appendChild(item);

    bootstrap.Collapse.getOrCreateInstance(
        document.getElementById(`payroll-employee-component-collapse-${index}`),
        { toggle: false }
    ).show();
}

function removePayrollEmployeeComponent(btn) {
    btn.closest(".payroll-employee-component-card").remove();
    calculatePayrollPreview();
}

function toggleDeductionBase(element) {
    const card = element.closest(".payroll-employee-component-card");
    const deductionBaseContainer = card.querySelector(".payroll-employee-deduction-base-container");
    const type = element.value;

    if (deductionBaseContainer) {
        if (type === "DEDUCTION") {
            deductionBaseContainer.style.display = "";
        } else {
            deductionBaseContainer.style.display = "none";
        }
    }
}

function updatePayrollEmployeeComponentSummary(element) {
    const card = element.closest(".payroll-employee-component-card");

    const name = card.querySelector(".component-name-select").value.trim() || "Unnamed Component";
    const type = card.querySelector(".component-type").value !== "0"
        ? card.querySelector(".component-type").value
        : "Type not selected";
    const amount = card.querySelector(".component-amount").value || "0";

    card.querySelector(".component-card-summary").innerText =
        `${name} • ${type} • ${Number(amount).toFixed(2)}`;
}

function collectPayrollEmployeeComponents() {
    const components = [];

    document.querySelectorAll(".payroll-employee-component-card").forEach((card, index) => {
        components.push({
            index: index + 1,
            component_id: card.dataset.componentId || 0,
            component_name: card.querySelector(".component-name-select").value.trim(),
            component_type: card.querySelector(".component-type").value,
            amount: card.querySelector(".component-amount").value || 0,
            is_epf_applicable: card.querySelector(".component-epf").value || 0,
            is_taxable: card.querySelector(".component-taxable").value || 0,
            deduction_base: card.querySelector(".component-employee-deduction-base")?.value || "GROSS",
            note: card.querySelector(".component-note").value.trim()
        });
    });

    return components;
}

function calculatePayrollPreview() {
    const basicSalary = Number(document.getElementById("basic-salary").value || 0);

    let earnings = 0;
    let deductions = 0;
    let epfApplicableEarnings = 0;

    document.querySelectorAll(".payroll-employee-component-card").forEach(card => {
        const type = card.querySelector(".component-type").value;
        const amount = Number(card.querySelector(".component-amount").value || 0);
        const isEpf = card.querySelector(".component-epf").value === "1";

        if (type === "EARNING") {
            earnings += amount;
            if (isEpf) epfApplicableEarnings += amount;
        }

        if (type === "DEDUCTION") {
            deductions += amount;
        }
    });

    const epfSalary = basicSalary + epfApplicableEarnings;
    const employeeEpf = epfSalary * 0.08;
    const employerEpf = epfSalary * 0.12;
    const etf = epfSalary * 0.03;

    const gross = basicSalary + earnings;
    const totalDeductions = deductions + employeeEpf;
    const net = gross - totalDeductions;

    document.getElementById("employee-epf").value = employeeEpf.toFixed(2);
    document.getElementById("employer-epf").value = employerEpf.toFixed(2);
    document.getElementById("etf").value = etf.toFixed(2);

    document.getElementById("preview-gross").innerText = gross.toFixed(2);
    document.getElementById("preview-deductions").innerText = totalDeductions.toFixed(2);
    document.getElementById("preview-net").innerText = net.toFixed(2);
}

function showPayrollEmployeeComponentError(index, inputClass, feedbackClass, message) {
    const card = document.querySelectorAll(".payroll-employee-component-card")[index - 1];
    if (!card) return;

    card.querySelector(inputClass)?.classList.add("is-invalid");

    const feedback = card.querySelector(feedbackClass);
    if (feedback) feedback.innerText = message;

    const collapse = card.querySelector(".accordion-collapse");
    bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
}

const openPayrollStatusModal = (payrollEmployeeId, currentStatus) => {
    console.log(currentStatus);
    $("#status-selector option").removeClass("d-none");
    if (currentStatus === "DRAFT") {
        $("#status-selector option[value='APPROVED']").addClass("d-none");
        $("#status-selector option[value='PAID']").addClass("d-none");
    }

    if (currentStatus === "VERIFIED") {
        $("#status-selector option[value='VERIFIED']").addClass("d-none");
        $("#status-selector option[value='PAID']").addClass("d-none");
    }
    if (currentStatus === "APPROVED") {
        $("#status-selector option[value='VERIFIED']").addClass("d-none");
        $("#status-selector option[value='APPROVED']").addClass("d-none");
    }
    $("#update-payroll-status")
        .off("click")
        .on("click", function () {
            updatePayrollEmployeeStatus(this, payrollEmployeeId);
        });

    $("#updatePayrollStatusModal").modal("show");
};

const openPayrollRunsStatusModal = (payrollEmployeeId, currentStatus) => {
    $("#status-selector option").removeClass("d-none");
    if (currentStatus === "CALCULATED") {
        $("#status-selector option[value='APPROVED']").addClass("d-none");
        $("#status-selector option[value='PAID']").addClass("d-none");
    }

    if (currentStatus === "VERIFIED") {
        $("#status-selector option[value='VERIFIED']").addClass("d-none");
        $("#status-selector option[value='PAID']").addClass("d-none");
    }
    if (currentStatus === "APPROVED") {
        $("#status-selector option[value='APPROVED']").addClass("d-none");
        $("#status-selector option[value='VERIFIED']").addClass("d-none");
    }
    $("#update-payroll-status")
        .off("click")
        .on("click", function () {
            updatePayrollRunsStatus(this, payrollEmployeeId);
        });

    $("#updatePayrollStatusModal").modal("show");
};

const openPayrollRunsMessageModal = (id, department) => {
    $("#pending-paysheets-link").attr("href", `./payroll-run-details?payroll-run-id=${id}&department=${department}`);
    $("#payrollRunMessageModal").modal("show");
}


var selectedAddLeaveUser = null;
function searchLeaveUser(keyword) {
    const box = document.getElementById("userSuggestions");

    if (keyword.length < 1) {
        box.innerHTML = "";
        return;
    }

    const filtered = users.filter(u =>
        u.name.toLowerCase().includes(keyword.toLowerCase())
    );

    let html = "";

    filtered.slice(0, 8).forEach(user => {
        selectedAddLeaveUser = user.id;
        html += `
            <div class="list-group-item list-group-item-action d-flex align-items-center"
               onclick="selectLeaveAnalysisUser('${user.id}', '${user.name}')"
                 style="cursor:pointer;">

                <img src="${user.img}" 
                     class="rounded-circle me-2" 
                     width="35" height="35"
                     onerror="this.src='assets/imgs/users/default-user.webp'">

                <span>${user.name}</span>
            </div>
        `;
    });

    box.innerHTML = html;
}

const initializeEditIntakePlan = (planId) => {
    loadPaymentType(planId);
}

const confirmDeletePlan = (id) => {
    $("#confirmRemoveIntakePaymentPlan").off("click");
    $("#confirmRemoveIntakePaymentPlan").on("click", function () {
        removeIntakePaymentPlan(id);
    });
    $("#removeIntakePaymentPlanConfirmModal").modal("show");
}

const initializeUserAttendance = () => {
    console.log("Initializing user attendance...");
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
}

const toggleRegPayment = (checkbox) => {
    const wrap = document.getElementById('regPaymentAmountWrap');
    const amtInput = document.getElementById('regPaymentAmount');
    const feedback = document.getElementById('regPaymentAmountFeedback');

    if (checkbox.checked) {
        wrap.classList.add('open');
        amtInput.focus();
    } else {
        wrap.classList.remove('open');
        // Clear value & error when toggled off
        amtInput.value = '';
        feedback.textContent = '';
    }
};

const toggleRegPaymentSection = (checkbox) => {
    const fields = document.getElementById('reg-payment-fields');
    const amtInput = document.getElementById('reg-payment-amount');
    const amtFb = document.getElementById('reg-payment-amount-feedback');
    const slipFb = document.getElementById('doc-payment-slip-feedback');

    if (checkbox.checked) {
        fields.classList.add('open');
    } else {
        fields.classList.remove('open');
        // Clear values and errors when disabled
        amtInput.value = '';
        amtFb.textContent = '';
        slipFb.textContent = '';
        // Clear the slip preview if your doc uploader exposes a reset method
        const slipInput = document.getElementById('doc-payment-slip');
        if (slipInput) {
            slipInput.value = '';
            const preview = slipInput.closest('.student-doc-upload-box')?.querySelector('.doc-preview');
            if (preview) preview.innerHTML = '';
        }
    }
};



const handleSelectedIntakePaymentPlanStudents = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterIntakePaymentPlanStudents(id);
}

function switchPaymentGroup(type) {
    const intakeBtn = document.getElementById('btn-intake-group');
    const customBtn = document.getElementById('btn-custom-group');
    const intakeSec = document.getElementById('section-intake-group');
    const customSec = document.getElementById('section-custom-group');
    const checkIntake = document.getElementById('check-intake');
    const checkCustom = document.getElementById('check-custom');

    const intakeIconEl = intakeBtn.querySelector('.pg-tb-icon iconify-icon');
    const customIconEl = customBtn.querySelector('.pg-tb-icon iconify-icon');

    if (type === 'intake') {
        intakeBtn.classList.add('active');
        customBtn.classList.remove('active');
        intakeSec.classList.remove('d-none');
        customSec.classList.add('d-none');

        intakeIconEl.style.color = '#fff';
        customIconEl.style.color = '#64748b';

        checkIntake.innerHTML = '<iconify-icon icon="mdi:check" width="11" height="11" style="color:#fff;"></iconify-icon>';
        checkCustom.innerHTML = '';

        loadIntakePaymentPlans();
    } else {
        customBtn.classList.add('active');
        intakeBtn.classList.remove('active');
        customSec.classList.remove('d-none');
        intakeSec.classList.add('d-none');

        customIconEl.style.color = '#fff';
        intakeIconEl.style.color = '#64748b';

        checkCustom.innerHTML = '<iconify-icon icon="mdi:check" width="11" height="11" style="color:#fff;"></iconify-icon>';
        checkIntake.innerHTML = '';
    }
}

let selectedIntakePlanId = null;

function selectIntakePaymentPlan(input) {

    $(".intake-plan-checkbox").not(input).prop("checked", false);

    if ($(input).is(":checked")) {
        selectedIntakePlanId = $(input).data("plan-id");
        $("#save-plan-btn").removeClass("d-none");
    } else {
        selectedIntakePlanId = null;
        $("#save-plan-btn").addClass("d-none");
    }
}
const handleEnrollmentPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterEnrollment();
}

const handlePaymentPlansPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;

    }

    filterPaymentPlans();
}

const changeRegPaymentAMount = (value) => {
    if (value == 'Other') {
        $("#custom-reg-amount-area").hasClass('d-none') ? $("#custom-reg-amount-area").removeClass('d-none') : '';
    } else {
        $("#custom-reg-amount-area").hasClass('d-none') ? '' : $("#custom-reg-amount-area").addClass('d-none');
    }
}

const handleUsersLecturersPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterLecturers();
}

const removeLectureOpenModal = (id) => {
    $("#confirmRemoveLecture").off("click");
    $("#confirmRemoveLecture").on("click", function () {
        removeLecture(id);
    });

    $("#removeLectureConfirmModal").modal("show");
}


function openInvoicePrintWindow(invoice) {
    const printWindow = window.open('', '_blank', 'width=1000,height=900');

    // A receipt can cover more than one payment (e.g. an installment payment
    // plus overpayment auto-applied to the next installment). Only break out
    // the itemized list when there's actually more than one payment under
    // this receipt; a normal single-payment receipt keeps the original layout.
    const items = Array.isArray(invoice.items) ? invoice.items : [];
    const hasMultipleItems = items.length > 1;

    const itemRows = hasMultipleItems
        ? `
                                <tr>
                                    <td colspan="2" style="padding-top:14px; font-weight:700; color:#4c555b;">Payments Covered by This Receipt</td>
                                </tr>` + items.map(item => `
                                <tr>
                                    <td>${item.description ?? 'Payment'}</td>
                                    <td class="amount-col">${item.amount ?? '0.00'}</td>
                                </tr>`).join('')
        : '';

    const html = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Invoice</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

      <style>
    @page {
        size: A4;
        margin: 0;
    }

    * {
        box-sizing: border-box;
    }

    html, body {
        margin: 0;
        padding: 0;
        width: 210mm;
        height: 297mm;
        font-family: Arial, Helvetica, sans-serif !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body {
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .print-page {
        width: 210mm;
        height: 297mm;
        background: #ffffff;
        position: relative;
        overflow: hidden;
    }

    .invoice-card {
        width: 100%;
        height: 100%;
        padding: 18mm 18mm 18mm 18mm;
        position: relative;
    }

    .invoice-body {
        padding-bottom: 32mm;
    }

    .brand-logo {
        width: 280px;
        max-width: 280px;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .company-address {
        margin-top: 8px;
        font-size: 20px;
        line-height: 1.45;
        color: #617188;
        font-weight: 500;
    }

    .invoice-title {
        text-align: right;
        padding-top: 2px;
    }

    .invoice-title h1 {
        margin: 0;
        font-size: 40px;
        line-height: 1.05;
        font-weight: 700;
        color: #4c555b;
        letter-spacing: 0.1px;
    }

    .invoice-number {
        margin-top: 9px;
        font-size: 25px;
        font-weight: 700;
        color: #4c555b;
        letter-spacing: 0.2px;
    }

    .info-section {
        margin-top: 36px;
        padding-bottom: 24px;
    }

    .info-label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #4c555b;
        margin-bottom: 6px;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .info-value {
        font-size: 18px;
        line-height: 1.3;
        color: #4c555b;
        font-weight: 600;
        white-space: nowrap;
    }

    .info-subvalue {
        font-size: 14px;
        line-height: 1.4;
        color: #9aa5b3;
        font-weight: 500;
        margin-top: 3px;
    }

    .meta-table {
        width: 100%;
        border-collapse: collapse;
    }

    .meta-table td {
        font-size: 15px;
        color: #617188;
        font-weight: 500;
        padding: 3px 0;
        white-space: nowrap;
    }

    .meta-table td.meta-label {
        color: #9aa5b3;
        padding-right: 18px;
    }

    .meta-table td.meta-value {
        text-align: right;
        color: #4c555b;
        font-weight: 700;
    }

    .invoice-table-wrap {
        margin-top: 32px;
    }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        border-top: 1px solid #F3F4F4;
    }

    .invoice-table thead th {
        font-size: 16px;
        font-weight: 700;
        color: #4c555b;
        padding: 8px 0;
        border-bottom: 1px solid #F3F4F4;
    }

    .invoice-table tbody td {
        font-size: 16px;
        color: #617188;
        padding: 8px 0;
        vertical-align: top;
        border-bottom: 1px solid #F3F4F4;
    }

    .payment-type-row td {
        font-size: 17px;
        font-weight: 700;
        color: #2f6fed;
        background: #f4f8ff;
        padding: 12px 10px;
        border-bottom: none;
    }

    .payment-type-row td:first-child {
        border-radius: 6px 0 0 6px;
    }

    .payment-type-row td:last-child {
        border-radius: 0 6px 6px 0;
    }

    .items-total-row td {
        font-size: 16px;
        font-weight: 700;
        color: #4c555b;
        border-top: 1px solid #d8dde5;
        border-bottom: 1px solid #F3F4F4;
    }

    .remarks-row td {
        border-bottom: none;
        padding-top: 9px;
        color: #9aa5b3;
        font-size: 14px;
    }

    .amount-col {
        text-align: right;
        white-space: nowrap;
    }

    .signature-section {
        margin-top: 54px;
        width: 220px;
    }

    .signature-name {
        font-size: 18px;
        color: #4c555b;
        font-weight: 700;
        border-bottom: 1px solid #d8dde5;
        padding-top: 8px;
    }

    .signature-caption {
        font-size: 13px;
        color: #9aa5b3;
        font-weight: 500;
        margin-top: 2px;
    }

    .invoice-footer {
        position: absolute;
        left: 18mm;
        right: 18mm;
        bottom: 14mm;
        border-top: 1px solid #d8dde5;
        padding-top: 9px;
        text-align: center;
    }

    .content-div-bottom {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .sub-text {
        font-size: 10px;
        color: #6e7b8c;
        margin: 0;
        line-height: 1.35;
        font-weight: 500;
    }

    .footer-link {
        text-decoration: none;
    }

    .enviiz-logo {
        width: 24px;
        height: auto;
        vertical-align: middle;
        margin-left: 3px;
    }

    @media print {
        html, body {
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .print-page {
            width: 210mm;
            height: 297mm;
            margin: 0;
            background: #ffffff;
        }

        .invoice-card {
            width: 100%;
            height: 100%;
            padding: 18mm 18mm 18mm 18mm;
        }

        .invoice-footer {
            position: absolute;
            left: 18mm;
            right: 18mm;
            bottom: 14mm;
        }
    }
</style>
    </head>
    <body>
        <div class="print-page">
            <div class="invoice-card">
                <div class="invoice-body">

                    <div class="row align-items-start">
                        <div class="col-7">
                            <img src="./assets/imgs/common/logo.png" alt="Logo" class="brand-logo">
                            <div class="company-address">
                                83 Katugastota Rd, Kandy 20000<br>
                                +94 777 00 18 28 / +94 704 80 08 00
                            </div>
                        </div>

                        <div class="col-5">
                            <div class="invoice-title">
                                <h1>Invoice</h1>
                                <div class="invoice-number">RECEIPT-${invoice.reference_no ?? '0000'}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row info-section">
                        <div class="col-6">
                            <span class="info-label">Billed To</span>
                            <div class="info-value">${invoice.student ?? 'N/A'}</div>
                            <div class="info-subvalue">Student ID: ${invoice.student_id ?? 'N/A'}</div>
                        </div>

                        <div class="col-6">
                            <span class="info-label" style="text-align:right; display:block;">Invoice Info</span>
                            <table class="meta-table">
                                <tr>
                                    <td class="meta-label">Invoice Date</td>
                                    <td class="meta-value">${invoice.printed_on ?? 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Payment Method</td>
                                    <td class="meta-value">${invoice.payment_method ?? invoice.cashier ?? 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Date Paid</td>
                                    <td class="meta-value">${invoice.date_on ?? 'N/A'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="invoice-table-wrap">
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="amount-col">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="payment-type-row">
                                    <td colspan="2"  style="padding: 5px;">${invoice.bill_title ?? 'Invoice Item'}</td>
                                </tr>
                                <tr>
                                    <td>Total Installment Fee</td>
                                    <td class="amount-col">${invoice.total_course_fee ?? '0.00'}</td>
                                </tr>
                                <tr>
                                    <td>Discount</td>
                                    <td class="amount-col">- ${invoice.discount_amount ?? '0.00'}</td>
                                </tr>
                                <tr>
                                    <td>Penalty</td>
                                    <td class="amount-col">+ ${invoice.penalty_amount ?? '0.00'}</td>
                                </tr>${itemRows}
                                <tr class="items-total-row">
                                    <td><strong>Paid This Receipt</strong></td>
                                    <td class="amount-col"><strong>${invoice.net_total ?? invoice.paid_amount ?? '0.00'}</strong></td>
                                </tr>
                                <tr>
                                    <td>Total Paid To Date</td>
                                    <td class="amount-col">${invoice.total_paid_amount ?? '0.00'}</td>
                                </tr>
                                <tr>
                                    <td style="color:#617188; font-weight:400;">Balance</td>
                                    <td class="amount-col" style="color:#617188; font-weight:400;">${invoice.balance ?? '0.00'}</td>
                                </tr>
                                <tr class="remarks-row">
                                    <td colspan="2">Remarks: ${invoice.remarks ?? '--'}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="signature-section">
                        <div class="signature-name">${invoice.prepared_by ?? 'N/A'}</div>
                        <div class="signature-caption">Prepared by</div>
                    </div>

                </div>

                <div class="invoice-footer">
                    <div class="content-div-bottom">
                        <p class="sub-text">
                            &copy;${new Date().getFullYear()} ENLIXO Uni. All Rights Reserved | Powered by
                            <a href="https://enviiz.com" target="_blank" class="footer-link">
                                <img src="./assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" class="enviiz-logo">
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.onload = function () {
                setTimeout(() => {
                    window.print();
                }, 400);
            };
        <\/script>
    </body>
    </html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
}

let selectedPayments = [];
let selectedAssignmentPayments = [];
let collectedAmount = 0;
function lecturerPaymentsCheckEvents() {
    const checkAll = document.getElementById('lp-check-all');
    const rowChecks = document.querySelectorAll('.lp-row-check');

    selectedPayments = [];

    rowChecks.forEach((checkbox) => {
        checkbox.checked = checkAll.checked;

        if (checkAll.checked) {
            selectedPayments.push(checkbox.value);
        }
    });

    updateSelectedPaymentAmount();
}

function lecuturePaymentsCheckRowEvents(paymentId) {
    const checkbox = document.getElementById("lp-" + paymentId);

    if (checkbox.checked) {
        if (!selectedPayments.includes(paymentId.toString())) {
            selectedPayments.push(paymentId.toString());
        }
    } else {
        selectedPayments = selectedPayments.filter(id => id !== paymentId.toString());
    }

    updateCheckAllStatus();
    updateSelectedPaymentAmount();
}

function updateCheckAllStatus() {
    const rowChecks = document.querySelectorAll('.lp-row-check');
    const checkedRows = document.querySelectorAll('.lp-row-check:checked');
    const checkAll = document.getElementById('lp-check-all');

    checkAll.checked = rowChecks.length === checkedRows.length;
}

function updateSelectedPaymentAmount() {
    let total = 0;

    selectedPayments.forEach((id) => {
        const checkbox = document.getElementById("lp-" + id);
        if (checkbox) {
            total += parseFloat(checkbox.dataset.amount) || 0;
        }
    });

    selectedAssignmentPayments.forEach((id) => {
        const checkbox = document.getElementById("ap-" + id);
        if (checkbox) {
            total += parseFloat(checkbox.dataset.amount) || 0;
        }
    })

    console.log(total);

    let balance = document.getElementById("totalBalance");
    const totalPaymentAmount = balance.dataset.total;

    const balanceAmount = totalPaymentAmount - total;
    balance.textContent = "LKR " + balanceAmount.toLocaleString("en-LK", { minimumFractionDigits: 2 });

    document.getElementById("totalCollected").textContent = "LKR " +
        total.toLocaleString("en-LK", { minimumFractionDigits: 2 });
    document.getElementById("modalTotalCollected").value = "LKR " +
        total.toLocaleString("en-LK", { minimumFractionDigits: 2 });

    collectedAmount = total;
}


function assignmentsPaymentsCheckEvents() {
    const checkAll = document.getElementById('ap-check-all');
    const rowChecks = document.querySelectorAll('.ap-row-check');

    selectedAssignmentPayments = [];

    rowChecks.forEach((checkbox) => {
        checkbox.checked = checkAll.checked;

        if (checkAll.checked) {
            selectedAssignmentPayments.push(checkbox.value);
        }
    });

    updateSelectedPaymentAmount();
}

function assignmentPaymentsCheckRowEvents(paymentId) {
    const checkbox = document.getElementById("ap-" + paymentId);

    if (checkbox.checked) {
        if (!selectedAssignmentPayments.includes(paymentId.toString())) {
            selectedAssignmentPayments.push(paymentId.toString());
        }
    } else {
        selectedAssignmentPayments = selectedAssignmentPayments.filter(id => id !== paymentId.toString());
    }

    updateAPCheckAllStatus();
    updateSelectedPaymentAmount();
}

function updateAPCheckAllStatus() {
    const rowChecks = document.querySelectorAll('.ap-row-check');
    const checkedRows = document.querySelectorAll('.ap-row-check:checked');
    const checkAll = document.getElementById('ap-check-all');

    checkAll.checked = rowChecks.length === checkedRows.length;
}



const openMarkAsPaidModal = () => {
    if (selectedPayments.length > 0 || selectedAssignmentPayments.length > 0) {
        $("#markAsPaidModal").modal("show");
    } else {
        showToast('warning', 'Please select at least one payment to mark as paid.');
    }
}

function openCancelScheduleModal(lectureId, lecturerName, lectureDate) {

    document.getElementById('cancelScheduleDetails').innerHTML =
        '<strong>' + lecturerName + '</strong><br>' + lectureDate;

    $("#confirmCancelSchedule").on("click", function () {
        confirmCancelSchedule(this, lectureId);
    });

    const modal = new bootstrap.Modal(document.getElementById('cancelScheduleModal'));
    modal.show();
}

const initializeLectureFeedbackReport = () => {
    let lfrCurrentPage = 1;
    let lfrRows = []; // last loaded page's rows, keyed by index — used by the view modal, no extra fetch needed

    // 5-tier rating -> same colour scale used in the student
    // feedback modal, expressed here via the existing
    // .table-btn-* classes from style.css.
    const LFR_RATING_TIERS = [{
        range: [1, 2],
        label: 'Bad',
        cls: 'table-btn-danger'
    },
    {
        range: [3, 4],
        label: 'Below Average',
        cls: 'table-btn-orange'
    },
    {
        range: [5, 6],
        label: 'Average',
        cls: 'table-btn-yellow'
    },
    {
        range: [7, 8],
        label: 'Good',
        cls: 'table-btn-yellow-green'
    },
    {
        range: [9, 10],
        label: 'Absolutely Good',
        cls: 'table-btn-success'
    },
    ];

    function ratingTier(rating) {
        return LFR_RATING_TIERS.find(t => rating >= t.range[0] && rating <= t.range[1]);
    }

    function ratingBadgeHtml(rating) {
        if (rating === null || rating === undefined) {
            return '<span class="text-12 sub-text">-</span>';
        }
        const tier = ratingTier(rating);
        return `<span class="table-btn ${tier.cls} lfr-rating-badge py-1 px-2 text-12">${rating}/10</span>`;
    }

    function truncate(text, max) {
        if (!text) return '';
        return text.length > max ? text.slice(0, max) + '…' : text;
    }

    function currentFilters() {
        return {
            faculty_id: document.getElementById('lfr-faculty-selector').value || 0,
            programme_id: document.getElementById('lfr-programme-selector').value || 0,
            intake_id: document.getElementById('lfr-intake-selector').value || 0,
            subject_id: document.getElementById('lfr-subject-selector').value || 0,
            lecture_id: document.getElementById('lfr-lecture-selector').value,
            status: document.getElementById('lfr-status-selector').value,
            rating: document.getElementById('lfr-rating-selector').value,
            start_date: document.getElementById('lfr-start-date').value,
            end_date: document.getElementById('lfr-end-date').value,
            search: document.getElementById('lfr-search-text').value.trim(),
        };
    }

    // Debounced search — mirrors the students list page's
    // onkeyup handler, just waits briefly so it doesn't fire
    // a request on every keystroke.
    let lfrSearchTimer = null;
    window.onLfrSearchKeyup = function () {
        clearTimeout(lfrSearchTimer);
        lfrSearchTimer = setTimeout(() => loadLectureFeedbackReport(1), 350);
    }

    function fetchFilterOptions(stage, extra) {
        return fetch('api/getLectureFeedbackFilterOptions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.assign({
                stage
            }, currentFilters(), extra || {}))
        }).then(res => res.json());
    }

    function resetSelector(selector, placeholder) {
        selector.innerHTML = `<option value="0" selected>${placeholder}</option>`;
    }

    window.onLfrFacultyChanged = function () {
        const programmeSelector = document.getElementById('lfr-programme-selector');
        const intakeSelector = document.getElementById('lfr-intake-selector');
        const subjectSelector = document.getElementById('lfr-subject-selector');

        resetSelector(programmeSelector, 'All Programmes');
        resetSelector(intakeSelector, 'All Intakes');
        resetSelector(subjectSelector, 'All Subjects');

        fetchFilterOptions('programmes').then(data => {
            (data.programmes || []).forEach(p => {
                programmeSelector.innerHTML += `<option value="${p.programme_id}">${p.programme}</option>`;
            });
        });

        refreshLfrIntakeOptions();
        refreshLfrSubjectOptions();
        loadLectureFeedbackReport(1);
    }

    window.onLfrProgrammeChanged = function () {
        resetSelector(document.getElementById('lfr-intake-selector'), 'All Intakes');
        resetSelector(document.getElementById('lfr-subject-selector'), 'All Subjects');
        refreshLfrIntakeOptions();
        refreshLfrSubjectOptions();
        loadLectureFeedbackReport(1);
    }

    window.onLfrIntakeChanged = function () {
        refreshLfrSubjectOptions();
        loadLectureFeedbackReport(1);
    }

    window.onLfrSubjectChanged = function () {
        resetSelector(document.getElementById('lfr-lecture-selector'), 'All Lectures');
        refreshLfrLectureOptions();
        loadLectureFeedbackReport(1);
    }

    function refreshLfrIntakeOptions() {
        const intakeSelector = document.getElementById('lfr-intake-selector');
        fetchFilterOptions('intakes').then(data => {
            resetSelector(intakeSelector, 'All Intakes');
            (data.intakes || []).forEach(i => {
                intakeSelector.innerHTML += `<option value="${i.intake_id}">${i.intake}</option>`;
            });
        });
    }

    function refreshLfrSubjectOptions() {
        const subjectSelector = document.getElementById('lfr-subject-selector');
        fetchFilterOptions('subjects').then(data => {
            resetSelector(subjectSelector, 'All Subjects');
            (data.subjects || []).forEach(sub => {
                subjectSelector.innerHTML += `<option value="${sub.subject_id}">${sub.subject}</option>`;
            });
        });
    }

    function studentAvatarHtml(s) {
        const initials = ((s.first_name[0] || '') + (s.last_name[0] || '')).toUpperCase();
        return s.profile_img ?
            `<img src="./assets/imgs/students/${s.profile_img}" class="hwr-40" alt="">` :
            `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${initials}</div>`;
    }

    function contextLineHtml(s) {
        return `<span class="text-12 sub-text">${s.faculty}<br>${s.programme} - ${s.intake}</span>`;
    }

    function statusPillHtml(submitted) {
        return submitted ?
            '<span class="lfr-status-pill submitted"><iconify-icon icon="mdi:check-circle" width="13"></iconify-icon>Submitted</span>' :
            '<span class="lfr-status-pill pending"><iconify-icon icon="mdi:clock-alert-outline" width="13"></iconify-icon>Pending</span>';
    }

    function fmtDate(d) {
        if (!d) return '-';
        return new Date(d).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    window.loadLectureFeedbackReport = function (page) {
        lfrCurrentPage = page || lfrCurrentPage;
        const feedback = document.getElementById('lfr-filter-feedback');
        feedback.innerText = '';

        const tableBody = document.getElementById('lfr-table-body');
        tableBody.innerHTML = '<tr id="lfr-table-loading"><td colspan="9" class="text-center py-4 text-14 sub-text">Loading report...</td></tr>';

        const perPage = document.getElementById('lfr-per-page-selector').value;

        fetch('api/getLectureFeedbackSummary.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.assign({
                page: lfrCurrentPage,
                per_page: perPage
            }, currentFilters()))
        })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    feedback.innerText = data.error;
                    tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">' + data.error + '</td></tr>';
                    return;
                }
                renderCards(data);
                renderTable(data.rows);
                renderPagination(data.total, data.page, data.per_page);
            })
            .catch(() => {
                feedback.innerText = 'Something went wrong loading the report. Please try again.';
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">Failed to load data.</td></tr>';
            });
    }

    function renderCards(data) {
        document.getElementById('lfr-value-total-feedback').innerText = data.total_submitted;
        document.getElementById('lfr-value-pending-feedback').innerText = data.total_pending;
        document.getElementById('lfr-value-response-rate').innerText = data.response_rate + '%';
        document.getElementById('lfr-value-avg-rating').innerText = data.average_rating;
        document.getElementById('lfr-record-count').innerText = data.total + (data.total === 1 ? ' Record' : ' Records');
    }

    function renderTable(rows) {
        lfrRows = rows || [];
        const tableBody = document.getElementById('lfr-table-body');

        if (!lfrRows.length) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">No records match this filter.</td></tr>';
            return;
        }

        let html = '';
        lfrRows.forEach((r, idx) => {
            const remarkHtml = r.submitted ?
                (r.remark ?
                    `<span class="lfr-remark-cell text-14 main-text" title="${(r.remark || '').replace(/"/g, '&quot;')}">${truncate(r.remark, 40)}</span>` :
                    '<span class="lfr-remark-empty text-13">No remark</span>') :
                '<span class="text-12 sub-text">-</span>';

            html += `
                            <tr>
                                <td class="px-4 py-3 align-middle">
                                    <div class="d-flex align-items-center">
                                        ${studentAvatarHtml(r)}
                                        <span class="text-14 main-text mx-2">${r.title}. ${r.first_name} ${r.last_name}</span>
                                    </div>
                                </td>
                                <td class="py-2 align-middle"><span class="text-14 main-text">${r.sId}</span></td>
                                <td class="py-2 align-middle">${contextLineHtml(r)}</td>
                                <td class="py-2 align-middle">
                                    <span class="text-14 main-text d-block">${r.subject}</span>
                                    <span class="text-12 sub-text">${r.lecture_type}</span>
                                </td>
                                <td class="py-2 align-middle text-center">${statusPillHtml(r.submitted)}</td>
                                <td class="py-2 align-middle text-center">${ratingBadgeHtml(r.rating)}</td>
                                <td class="py-2 align-middle">${remarkHtml}</td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${r.submitted ? fmtDate(r.submitted_at) : '-'}</span></td>
                                <td class="py-2 align-middle text-center">
                                    <div class="dropdown lfr-actions-dropdown">
                                        <button class="btn-three-dot" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <iconify-icon icon="mdi:dots-vertical" width="18"></iconify-icon>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item cursor-pointer" onclick="openLfrViewModal(${idx})">
                                                <iconify-icon icon="mdi:eye-outline" width="16" class="me-2"></iconify-icon>View
                                            </a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        `;
        });

        tableBody.innerHTML = html;
    }

    window.openLfrViewModal = function (idx) {
        const r = lfrRows[idx];
        if (!r) return;

        document.getElementById('lfr-modal-avatar').innerHTML = studentAvatarHtml(r);
        document.getElementById('lfr-modal-student-name').innerText = `${r.title}. ${r.first_name} ${r.last_name}`;
        document.getElementById('lfr-modal-student-id').innerText = r.sId;
        document.getElementById('lfr-modal-status-pill').innerHTML = statusPillHtml(r.submitted);

        document.getElementById('lfr-modal-faculty').innerText = r.faculty || '-';
        document.getElementById('lfr-modal-programme').innerText = r.programme || '-';
        document.getElementById('lfr-modal-intake').innerText = r.intake || '-';
        document.getElementById('lfr-modal-subject').innerText = r.subject || '-';
        document.getElementById('lfr-modal-lecture-type').innerText = r.lecture_type || '-';
        document.getElementById('lfr-modal-lecture-date').innerText = fmtDate(r.lecture_date);
        document.getElementById('lfr-modal-rating').innerHTML = ratingBadgeHtml(r.rating);
        document.getElementById('lfr-modal-submitted-on').innerText = r.submitted ? fmtDate(r.submitted_at) : 'Not submitted yet';
        document.getElementById('lfr-modal-remark').innerText = r.submitted ? (r.remark || 'No remark left.') : 'Feedback not submitted for this lecture yet.';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('lfrViewModal')).show();
    }

    // Mirrors the PHP renderPageButton()/shudder-dots logic from
    // the students list pagination: first page, last page, and
    // the 2 pages either side of the current one are shown as
    // numbers; any gap collapses to a single "..." once.
    function renderPagination(total, page, perPage) {
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        const list = document.getElementById('lfr-pagination-list');
        list.innerHTML = '';

        let shudder1 = false;
        let shudder2 = false;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || Math.abs(page - i) <= 2) {
                const li = document.createElement('li');
                li.className = 'page-item' + (page === i ? ' active' : '');
                li.id = 'lfr-page-' + i;
                const action = page === i ? '' : `onclick="loadLectureFeedbackReport(${i})"`;
                li.innerHTML = `<button class="py-2 px-3" type="button" ${action}>${i}</button>`;
                list.appendChild(li);
            } else if (!shudder1 && i < page - 2 && i > 1) {
                shudder1 = true;
                const li = document.createElement('li');
                li.className = 'd-flex align-items-end h-100';
                li.innerHTML = '<iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon>';
                list.appendChild(li);
            } else if (!shudder2 && i > page + 2 && i < totalPages) {
                shudder2 = true;
                const li = document.createElement('li');
                li.className = 'd-flex align-items-end h-100';
                li.innerHTML = '<iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon>';
                list.appendChild(li);
            }
        }

        const prevBtn = document.getElementById('lfr-prev-btn');
        const nextBtn = document.getElementById('lfr-next-btn');
        prevBtn.disabled = page <= 1;
        nextBtn.disabled = page >= totalPages;
        prevBtn.onclick = () => {
            if (page > 1) loadLectureFeedbackReport(page - 1);
        };
        nextBtn.onclick = () => {
            if (page < totalPages) loadLectureFeedbackReport(page + 1);
        };
    }

    loadLectureFeedbackReport(1);
    console.log(currentFilters());
}

const initializeAttendanceBreakdown = () => {
    let bdMode = 'month'; // 'month' | 'range'
    let bdTrendChart = null;
    let bdStatusChart = null;

    window.setBreakdownMode = function (mode) {
        bdMode = mode;

        const monthBtn = document.getElementById('bd-mode-month');
        const rangeBtn = document.getElementById('bd-mode-range');

        monthBtn.className = (mode === 'month') ? 'custom-btn' : 'custom-outline-btn';
        rangeBtn.className = (mode === 'range') ? 'custom-btn' : 'custom-outline-btn';

        document.getElementById('bd-month-wrapper').classList.toggle('d-none', mode !== 'month');
        document.getElementById('bd-range-wrapper').classList.toggle('d-none', mode !== 'range');
        loadBreakdown();
    }

    function getSelectedDateWindow() {
        if (bdMode === 'month') {
            const monthVal = document.getElementById('bd-month-input').value; // YYYY-MM
            if (!monthVal) return null;
            const [y, m] = monthVal.split('-').map(Number);
            const start = `${y}-${String(m).padStart(2, '0')}-01`;
            const lastDay = new Date(y, m, 0).getDate();
            const end = `${y}-${String(m).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
            return {
                start,
                end
            };
        } else {
            const start = document.getElementById('bd-start-date').value;
            const end = document.getElementById('bd-end-date').value;
            if (!start || !end) return null;
            return {
                start,
                end
            };
        }
    }

    function pctBadgeClass(pct) {
        if (pct >= 80) return 'table-btn-success';
        if (pct >= 65) return 'table-btn-yellow';
        return 'table-btn-danger';
    }

    function currentFilters() {
        return {
            faculty_id: document.getElementById('faculty-selector').value,
            programme_id: document.getElementById('programme-selector').value,
            intake_id: document.getElementById('intake-selector').value,
        };
    }

    function fetchFilterOptions(stage, extra) {
        console.log(stage);
        return fetch('api/getCounselorFilterOptions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.assign({
                stage
            }, currentFilters(), extra || {}))
        }).then(res => res.json());
    }

    function resetSelector(selector, placeholder) {
        selector.innerHTML = `<option value="0" selected>${placeholder}</option>`;
    }

    // Faculty narrowed -> reload Programme/Intake/Subject options underneath it.
    window.onFacultyChanged = function () {
        const programmeSelector = document.getElementById('programme-selector');
        const intakeSelector = document.getElementById('intake-selector');
        const subjectSelector = document.getElementById('subject-selector');

        resetSelector(programmeSelector, 'All Programmes');
        resetSelector(intakeSelector, 'All Intakes');
        resetSelector(subjectSelector, 'All Subjects');

        fetchFilterOptions('programmes').then(data => {
            (data.programmes || []).forEach(p => {
                programmeSelector.innerHTML += `<option value="${p.programme_id}">${p.programme}</option>`;
            });
        });

        refreshIntakeOptions();
        refreshSubjectOptions();
        loadBreakdown();
    }

    // Programme narrowed -> reload Intake/Subject options underneath it.
    window.onProgrammeChanged = function () {
        const intakeSelector = document.getElementById('intake-selector');
        const subjectSelector = document.getElementById('subject-selector');

        resetSelector(intakeSelector, 'All Intakes');
        resetSelector(subjectSelector, 'All Subjects');

        refreshIntakeOptions();
        refreshSubjectOptions();
        loadBreakdown();
    }

    // Intake narrowed -> reload Subject options underneath it.
    window.onIntakeChanged = function () {
        refreshSubjectOptions();
        loadBreakdown();
    }

    function refreshIntakeOptions() {
        const intakeSelector = document.getElementById('intake-selector');
        fetchFilterOptions('intakes').then(data => {
            resetSelector(intakeSelector, 'All Intakes');
            (data.intakes || []).forEach(i => {
                intakeSelector.innerHTML += `<option value="${i.intake_id}">${i.intake}</option>`;
            });
        });
    }

    function refreshSubjectOptions() {
        const subjectSelector = document.getElementById('subject-selector');
        fetchFilterOptions('subjects').then(data => {
            resetSelector(subjectSelector, 'All Subjects');
            let currentLevel = null;
            let optionsHtml = subjectSelector.innerHTML;
            (data.subjects || []).forEach(sub => {
                if (sub.level !== currentLevel) {
                    if (currentLevel !== null) optionsHtml += '</optgroup>';
                    optionsHtml += `<optgroup label="${sub.level}">`;
                    currentLevel = sub.level;
                }
                optionsHtml += `<option value="${sub.subject_id}">${sub.subject}</option>`;
            });
            if (currentLevel !== null) optionsHtml += '</optgroup>';
            subjectSelector.innerHTML = optionsHtml;
        });
    }

    window.loadBreakdown = function () {
        const feedback = document.getElementById('bd-filter-feedback');
        feedback.innerText = '';

        const window_ = getSelectedDateWindow();
        if (!window_) {
            feedback.innerText = 'Please select a valid date range.';
            return;
        }

        const tableBody = document.getElementById('bd-table-body');
        if (tableBody) tableBody.innerHTML = '<tr id="bd-table-loading"><td colspan="9" class="text-center py-4 text-14 sub-text">Loading breakdown...</td></tr>';

        const atRiskBody = document.getElementById('bd-at-risk-body');
        if (atRiskBody) atRiskBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-14 bd-at-risk-empty">Loading...</td></tr>';

        const subjectSelector = document.getElementById('subject-selector');

        fetch('api/getAttendanceBreakdown.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.assign({
                subject_id: subjectSelector ? subjectSelector.value : 0,
                start_date: window_.start,
                end_date: window_.end
            }, currentFilters()))
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.error) {
                    feedback.innerText = data.error;
                    if (tableBody) tableBody.innerHTML = '<tr id="bd-table-loading"><td colspan="9" class="text-center py-4 text-14 sub-text">' + data.error + '</td></tr>';
                    if (atRiskBody) atRiskBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-14 bd-at-risk-empty">' + data.error + '</td></tr>';
                    return;
                }
                renderCards(data);
                renderTrendChart(data.trend);
                renderStatusChart(data.status_breakdown);
                renderStudentTable(data.students);
                renderAtRiskTable(data.at_risk);
            })
            .catch((error) => {
                console.log(error);
                feedback.innerText = 'Something went wrong loading the breakdown. Please try again.';
                if (tableBody) tableBody.innerHTML = '<tr id="bd-table-loading"><td colspan="9" class="text-center py-4 text-14 sub-text">Failed to load data.</td></tr>';
                if (atRiskBody) atRiskBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-14 bd-at-risk-empty">Failed to load data.</td></tr>';
            });
    }

    function renderCards(data) {
        document.getElementById('bd-value-total-students').innerText = data.total_students;
        document.getElementById('bd-value-lectures-held').innerText = data.lectures_held;
        document.getElementById('bd-value-avg-attendance').innerText = data.average_attendance + '%';
        document.getElementById('bd-value-at-risk-count').innerText = (data.at_risk || []).length;

        document.getElementById('bd-at-risk-count-badge').innerText = (data.at_risk || []).length;
        document.getElementById('bd-students-count-badge').innerText = (data.students || []).length;
    }

    function renderTrendChart(trend) {
        const ctx = document.getElementById('bd-trend-chart').getContext('2d');
        const labels = trend.map(t => t.date_label);
        const values = trend.map(t => t.attendance_pct);

        if (bdTrendChart) {
            bdTrendChart.data.labels = labels;
            bdTrendChart.data.datasets[0].data = values;
            bdTrendChart.update();
            return;
        }

        bdTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance %',
                    data: values,
                    borderColor: '#F0C71F',
                    backgroundColor: 'rgba(240,199,31,0.12)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: v => v + '%'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    function renderStatusChart(statusBreakdown) {
        const ctx = document.getElementById('bd-status-chart').getContext('2d');
        const labels = ['Present', 'Watched', 'Half Verify', 'Absent'];
        const values = [
            statusBreakdown.PRESENT || 0,
            statusBreakdown.WATCHED || 0,
            statusBreakdown.HALF_VERIFY || 0,
            statusBreakdown.ABSENT || 0,
        ];
        const colors = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'];

        if (bdStatusChart) {
            bdStatusChart.data.datasets[0].data = values;
            bdStatusChart.update();
            return;
        }

        bdStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }

    function studentAvatarHtml(s) {
        const initials = ((s.first_name[0] || '') + (s.last_name[0] || '')).toUpperCase();
        return s.profile_img ?
            `<img src="./assets/imgs/students/${s.profile_img}" class="hwr-40" alt="">` :
            `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${initials}</div>`;
    }

    function contextLineHtml(s) {
        return `<span class="text-12 sub-text bd-context-line">${s.faculty}<br>${s.programme} - ${s.intake}</span>`;
    }

    function renderStudentTable(students) {
        const tableBody = document.getElementById('bd-table-body');

        if (!students || students.length === 0) {
            tableBody.innerHTML = '<tr id="bd-table-loading"><td colspan="9" class="text-center py-4 text-14 sub-text">No lectures found for this selection in the chosen period.</td></tr>';
            return;
        }

        let html = '';
        students.forEach(s => {
            const rowClass = s.absence_streak >= 3 ? 'bd-at-risk-row' : '';
            html += `
                            <tr class="${rowClass}">
                                <td class="px-4 py-3 align-middle">
                                    <div class="d-flex align-items-center">
                                        ${studentAvatarHtml(s)}
                                        <span class="text-14 main-text mx-2">${s.title}. ${s.first_name} ${s.last_name}</span>
                                    </div>
                                </td>
                                <td class="py-2 align-middle"><span class="text-14 main-text">${s.sId}</span></td>
                                <td class="py-2 align-middle">${contextLineHtml(s)}</td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${s.present_days}</span></td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${s.watched_days}</span></td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${s.half_verify_days}</span></td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${s.absent_days}</span></td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${s.lectures_held}</span></td>
                                <td class="py-2 align-middle text-center">
                                    <span class="table-btn ${pctBadgeClass(s.attendance_pct)} d-inline-flex align-items-center py-1 px-2 text-12">${s.attendance_pct}%</span>
                                </td>
                            </tr>
                        `;
        });

        tableBody.innerHTML = html;
    }

    function renderAtRiskTable(atRisk) {
        const atRiskBody = document.getElementById('bd-at-risk-body');

        if (!atRisk || atRisk.length === 0) {
            atRiskBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-14 bd-at-risk-empty">No students with 3+ consecutive absences in this period.</td></tr>';
            return;
        }

        let html = '';
        atRisk.forEach(s => {
            const lastAttended = s.last_attended_date ?
                new Date(s.last_attended_date).toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short'
                }) :
                'Never in period';

            html += `
                            <tr class="bd-at-risk-row">
                                <td class="px-4 py-3 align-middle">
                                    <div class="d-flex align-items-center">
                                        ${studentAvatarHtml(s)}
                                        <span class="text-14 main-text mx-2">${s.title}. ${s.first_name} ${s.last_name}</span>
                                    </div>
                                </td>
                                <td class="py-2 align-middle"><span class="text-14 main-text">${s.sId}</span></td>
                                <td class="py-2 align-middle">${contextLineHtml(s)}</td>
                                <td class="py-2 align-middle text-center">
                                    <span class="table-btn bd-streak-badge d-inline-flex align-items-center py-1 px-2 text-12">${s.absence_streak} in a row</span>
                                </td>
                                <td class="py-2 align-middle text-center"><span class="text-14 main-text">${lastAttended}</span></td>
                            </tr>
                        `;
        });

        atRiskBody.innerHTML = html;
    }

    // Page open = load the counselor's full roster breakdown straight
    // away, no selection required first.
    loadBreakdown();
}

const initializeUniformBreakdown = () => {
    // TODO: point this at your existing student payment page (shi id is appended)
    const PAYMENT_PAGE_URL = './add-receipt-to-student-payment.php?payment-id=';
    const STAGES = ['faculty', 'programme', 'intake'];
    const LABELS = ['All Faculties', 'All Programmes', 'All Intakes'];
    const charts = {};
    let studentMap = {};
    let confirmShiId = 0;
    let searchTimer = null;

    const el = id => document.getElementById(id);
    const selector = i => el(STAGES[i] + '-selector');
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[c]));
    const money = n => Number(n || 0).toLocaleString('en-LK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    }).then(r => r.json());

    function filters() {
        return {
            faculty_id: el('faculty-selector').value,
            programme_id: el('programme-selector').value,
            intake_id: el('intake-selector').value,
            payment_status: el('payment-status-selector').value,
            uniform_status: el('uniform-status-selector').value,
            search: el('ub-search').value.trim()
        };
    }

    function loadOptions(i) {
        return post('api/getUniformBreakdown.php', Object.assign({
            action: 'options',
            stage: STAGES[i]
        }, filters())).then(d => {
            console.log(d);
            const sel = selector(i);
            sel.innerHTML = `<option value="0">${LABELS[i]}</option>` +
                (d.options || []).map(o => `<option value="${o.id}">${esc(o.name)}</option>`).join('');
        });
    }

    // A selector changed: reset + reload everything beneath it, then refresh the data.
    window.onSelectorChanged = function (idx) {
        const jobs = [];
        for (let j = idx + 1; j < STAGES.length; j++) {
            selector(j).innerHTML = `<option value="0">${LABELS[j]}</option>`;
        }
        for (let j = idx + 1; j < STAGES.length; j++) jobs.push(loadOptions(j));
        Promise.all(jobs).then(loadBreakdown);
    }

    window.onSearchInput = function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadBreakdown, 300);
    }

    window.loadBreakdown = function () {
        el('ub-feedback').innerText = '';
        el('ub-table-body').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">Loading...</td></tr>';
        post('api/getUniformBreakdown.php', filters()).then(data => {
            if (data.error) {
                el('ub-feedback').innerText = data.error;
                el('ub-table-body').innerHTML = `<tr><td colspan="9" class="text-center py-4 text-14 sub-text">${esc(data.error)}</td></tr>`;
                return;
            }
            renderCards(data.summary);
            renderCharts(data.summary);
            renderTable(data.students);
        }).catch(() => {
            el('ub-feedback').innerText = 'Something went wrong loading the breakdown. Please try again.';
            el('ub-table-body').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">Failed to load data.</td></tr>';
        });
    }

    function renderCards(s) {
        el('ub-value-total').innerText = s.total;
        el('ub-value-pending-payments').innerText = s.pending_payments;
        el('ub-value-payments-received').innerText = s.payments_received;
        el('ub-value-uniforms-pending').innerText = s.uniforms_pending;
        el('ub-value-uniforms-received').innerText = s.uniforms_received;
        el('ub-value-collected').innerText = money(s.collected);
    }

    function drawChart(key, canvasId, type, labels, values, colors, extra) {
        if (charts[key]) {
            charts[key].data.datasets[0].data = values;
            charts[key].update();
            return;
        }
        charts[key] = new Chart(el(canvasId).getContext('2d'), {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false
            }, extra)
        });
    }

    function renderCharts(s) {
        const legend = {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        };
        drawChart('pay', 'ub-payment-chart', 'doughnut', ['Payment Received', 'Pending Payment'], [s.payments_received, s.pending_payments], ['#22c55e', '#f59e0b'], legend);
        drawChart('uni', 'ub-uniform-chart', 'doughnut', ['Uniform Received', 'Pending Uniform'], [s.uniforms_received, s.uniforms_pending], ['#3b82f6', '#ef4444'], legend);
        drawChart('fee', 'ub-fee-chart', 'bar', ['Collected', 'Outstanding'], [s.collected, s.outstanding], ['#22c55e', '#ef4444'], {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        });
    }

    function actionHtml(s) {
        const item = (icon, label, attrs) => `
                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" ${attrs}>
                                    <iconify-icon icon="${icon}" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                    ${label}</a></li>`;

        let items = '';
        if (s.uniform_status === 'RECEIVED') {
            items = item('mdi:eye-outline', 'View Details', `href="#" onclick="viewDetails(${s.shi_id}); return false;"`);
        } else if (s.payment_status === 'PENDING') {
            items = item('token:pay', 'Make Payment', `href="${PAYMENT_PAGE_URL}${s.course_payments_id}"`);
        } else {
            items = item('mdi:check-decagram-outline', 'Confirm Uniform Distribution', `href="#" onclick="openConfirm(${s.shi_id}); return false;"`);
        }

        return `
                                <div class="dropdown">
                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">${items}</ul>
                                </div>`;
    }

    function renderTable(students) {
        studentMap = {};
        if (!students || !students.length) {
            el('ub-table-body').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">No students match the selected filters.</td></tr>';
            return;
        }
        el('ub-table-body').innerHTML = students.map(s => {
            studentMap[s.shi_id] = s;
            const initials = ((s.first_name[0] || '') + (s.last_name[0] || '')).toUpperCase();
            const avatar = s.profile_img ?
                `<img src="./assets/imgs/students/${esc(s.profile_img)}" class="hwr-40" alt="">` :
                `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${esc(initials)}</div>`;
            const payOk = s.payment_status === 'RECEIVED';
            const uniOk = s.uniform_status === 'RECEIVED';
            return `
                            <tr>
                                <td class="px-4 py-3 align-middle"><div class="d-flex align-items-center">${avatar}
                                    <span class="text-14 main-text mx-2">${esc(s.title)}. ${esc(s.first_name)} ${esc(s.last_name)}</span></div></td>
                                <td class="py-2 align-middle"><span class="text-14 main-text">${esc(s.sId)}</span></td>
                                <td class="py-2 align-middle"><span class="text-12 sub-text ub-context-line">${esc(s.faculty)}<br>${esc(s.programme)} - ${esc(s.intake)}</span></td>
                                <td class="py-2 align-middle text-end text-14 main-text">${money(s.scheduled)}</td>
                                <td class="py-2 align-middle text-end text-14 main-text">${money(s.paid)}</td>
                                <td class="py-2 align-middle text-end text-14 main-text">${money(s.outstanding)}</td>
                                <td class="py-2 align-middle text-center"><span class="table-btn ${payOk ? 'table-btn-success' : 'table-btn-yellow'} d-inline-flex py-1 px-2 text-12">${payOk ? 'Payment Received' : 'Pending Payment'}</span></td>
                                <td class="py-2 align-middle text-center"><span class="table-btn ${uniOk ? 'table-btn-success' : 'table-btn-danger'} d-inline-flex py-1 px-2 text-12">${uniOk ? 'Uniform Received' : 'Pending Uniform'}</span></td>
                                <td class="py-2 align-middle text-center">${actionHtml(s)}</td>
                            </tr>`;
        }).join('');
    }

    window.openConfirm = function (shiId) {
        const s = studentMap[shiId];
        if (!s) return;
        confirmShiId = shiId;
        el('ub-note').value = '';
        el('ub-confirm-error').innerText = '';
        el('ub-confirm-btn').disabled = false;
        el('ub-confirm-msg').innerText = `Confirm that ${s.first_name} ${s.last_name} (${s.sId}) has received their uniform? This cannot be undone.`;
        bootstrap.Modal.getOrCreateInstance(el('ub-confirm-modal')).show();
    }

    window.submitConfirmation = function () {
        const btn = el('ub-confirm-btn');
        btn.disabled = true; // prevents double submit
        post('api/uniformDistribution.php', {
            action: 'confirm',
            shi_id: confirmShiId,
            note: el('ub-note').value.trim()
        }).then(d => {
            if (d.error) {
                showToast("error", "Uniform distribution confirmation failed");
                el('ub-confirm-error').innerText = d.error;
                btn.disabled = false;
                return;
            }
            showToast("success", "Uniform distribution confirmed successfully");
            bootstrap.Modal.getOrCreateInstance(el('ub-confirm-modal')).hide();
            loadBreakdown();
        }).catch((e) => {
            console.error(e);
            el('ub-confirm-error').innerText = 'Something went wrong. Please try again.';
            btn.disabled = false;
        });
    }

    window.viewDetails = function (shiId) {
        el('ub-details-body').innerHTML = '<span class="text-14 sub-text">Loading...</span>';
        bootstrap.Modal.getOrCreateInstance(el('ub-details-modal')).show();
        post('api/uniformDistribution.php', {
            action: 'details',
            shi_id: shiId
        }).then(d => {
            if (d.error) {
                el('ub-details-body').innerHTML = `<span class="text-14 text-dark-red">${esc(d.error)}</span>`;
                return;
            }
            const x = d.details;
            el('ub-details-body').innerHTML = `
                                <div class="text-14 main-text mb-2"><strong>Confirmed by:</strong> ${esc(x.confirmed_by || '-')}</div>
                                <div class="text-14 main-text mb-2"><strong>Date &amp; time:</strong> ${esc(x.confirmed_at)}</div>
                                <div class="text-14 main-text"><strong>Notes:</strong><br>${esc(x.note || 'No notes').replace(/\n/g, '<br>')}</div>`;
        });
    }

    // Initial load: all dropdowns, then the full breakdown.
    Promise.all(STAGES.map((_, i) => loadOptions(i))).then(loadBreakdown);
}

const initializeSelectedIntakeAttendnace = (id) => {
    const BD_INTAKE_ID = id;
    const collapseEl = document.getElementById('flush-collapseFour');
    if (!collapseEl) return; // no permission, accordion not rendered

    let bdMode = 'month';
    let bdTrendChart = null;
    let bdStatusChart = null;
    let bdLoaded = false;

    // Lazy load: only when the accordion is opened the first time
    collapseEl.addEventListener('shown.bs.collapse', function () {
        if (bdLoaded) return;
        bdLoaded = true;
        bdLoadSubjects();
        bdLoadBreakdown();
    });

    window.bdSetMode = function (mode) {
        bdMode = mode;
        document.getElementById('bd-mode-month').className = mode === 'month' ? 'custom-btn' : 'custom-outline-btn';
        document.getElementById('bd-mode-range').className = mode === 'range' ? 'custom-btn' : 'custom-outline-btn';
        document.getElementById('bd-month-wrapper').classList.toggle('d-none', mode !== 'month');
        document.getElementById('bd-range-wrapper').classList.toggle('d-none', mode !== 'range');
        bdLoadBreakdown();
    };

    function bdGetWindow() {
        if (bdMode === 'month') {
            const v = document.getElementById('bd-month-input').value;
            if (!v) return null;
            const [y, m] = v.split('-').map(Number);
            const last = new Date(y, m, 0).getDate();
            const mm = String(m).padStart(2, '0');
            return {
                start: `${y}-${mm}-01`,
                end: `${y}-${mm}-${String(last).padStart(2, '0')}`
            };
        }
        const start = document.getElementById('bd-start-date').value;
        const end = document.getElementById('bd-end-date').value;
        return (start && end) ? { start, end } : null;
    }

    function bdLoadSubjects() {
        fetch('api/getCounselorFilterOptions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                stage: 'subjects',
                faculty_id: 0,
                programme_id: 0,
                intake_id: BD_INTAKE_ID
            })
        })
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('bd-subject-selector');
                let html = '<option value="0" selected>All Subjects</option>';
                let currentLevel = null;
                (data.subjects || []).forEach(s => {
                    if (s.level !== currentLevel) {
                        if (currentLevel !== null) html += '</optgroup>';
                        html += `<optgroup label="${s.level}">`;
                        currentLevel = s.level;
                    }
                    html += `<option value="${s.subject_id}">${s.subject}</option>`;
                });
                if (currentLevel !== null) html += '</optgroup>';
                sel.innerHTML = html;
            })
            .catch(console.log);
    }

    window.bdLoadBreakdown = function () {
        const feedback = document.getElementById('bd-filter-feedback');
        feedback.innerText = '';
        const w = bdGetWindow();
        if (!w) {
            feedback.innerText = 'Please select a valid date range.';
            return;
        }

        document.getElementById('bd-table-body').innerHTML =
            '<tr><td colspan="8" class="text-center py-4 text-14 sub-text">Loading breakdown...</td></tr>';
        document.getElementById('bd-at-risk-body').innerHTML =
            '<tr><td colspan="4" class="text-center py-4 text-14 bd-at-risk-empty">Loading...</td></tr>';

        fetch('api/getAttendanceBreakdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                faculty_id: 0,
                programme_id: 0,
                intake_id: BD_INTAKE_ID,
                subject_id: document.getElementById('bd-subject-selector').value,
                start_date: w.start,
                end_date: w.end
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    feedback.innerText = data.error;
                    document.getElementById('bd-table-body').innerHTML =
                        `<tr><td colspan="8" class="text-center py-4 text-14 sub-text">${data.error}</td></tr>`;
                    document.getElementById('bd-at-risk-body').innerHTML =
                        `<tr><td colspan="4" class="text-center py-4 text-14 bd-at-risk-empty">${data.error}</td></tr>`;
                    return;
                }
                bdRenderCards(data);
                bdRenderTrend(data.trend || []);
                bdRenderStatus(data.status_breakdown || {});

                // These two are GLOBAL functions defined in the page's inline script.
                // They feed PagedTable so search / filters / sort / per-page / pagination work.
                bdRenderStudents(data.students);
                bdRenderAtRisk(data.at_risk);
            })
            .catch(err => {
                console.log(err);
                feedback.innerText = 'Something went wrong loading the breakdown. Please try again.';
            });
    };

    function bdRenderCards(d) {
        document.getElementById('bd-value-total-students').innerText = d.total_students;
        document.getElementById('bd-value-lectures-held').innerText = d.lectures_held;
        document.getElementById('bd-value-avg-attendance').innerText = d.average_attendance + '%';
        document.getElementById('bd-value-at-risk-count').innerText = (d.at_risk || []).length;
    }

    function bdRenderTrend(trend) {
        const labels = trend.map(t => t.date_label);
        const values = trend.map(t => t.attendance_pct);
        if (bdTrendChart) {
            bdTrendChart.data.labels = labels;
            bdTrendChart.data.datasets[0].data = values;
            bdTrendChart.update();
            return;
        }
        bdTrendChart = new Chart(document.getElementById('bd-trend-chart').getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Attendance %',
                    data: values,
                    borderColor: '#F0C71F',
                    backgroundColor: 'rgba(240,199,31,0.12)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: v => v + '%' }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    function bdRenderStatus(sb) {
        const values = [sb.PRESENT || 0, sb.WATCHED || 0, sb.HALF_VERIFY || 0, sb.ABSENT || 0];
        if (bdStatusChart) {
            bdStatusChart.data.datasets[0].data = values;
            bdStatusChart.update();
            return;
        }
        bdStatusChart = new Chart(document.getElementById('bd-status-chart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Watched', 'Half Verify', 'Absent'],
                datasets: [{
                    data: values,
                    backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 11 } }
                    }
                }
            }
        });
    }
}

function openProgrammeDocumentsModal(programmeId, programmeName) {
    const list = document.getElementById("programme-documents-list");
    const feedback = document.getElementById("programme-documents-feedback");
    const saveBtn = document.getElementById("programme-documents-save");

    document.getElementById("programme-documents-programme-id").value = programmeId;
    document.getElementById("programme-documents-programme-name").textContent = programmeName;
    feedback.textContent = "";
    saveBtn.disabled = true;

    list.innerHTML = "";
    const loading = document.createElement("div");
    loading.className = "col-12";
    loading.innerHTML = '<span class="sub-text text-14">Loading documents...</span>';
    list.appendChild(loading);

    bootstrap.Modal.getOrCreateInstance(document.getElementById("programmeDocumentsModal")).show();

    fetch("api/programmeDocuments.php?programme-id=" + encodeURIComponent(programmeId))
        .then((res) => res.json())

        .then((response) => {
            if (response.error) {
                list.innerHTML = "";
                feedback.textContent = response.error;
                return;
            }
            renderProgrammeDocuments(response.documents);
            saveBtn.disabled = false;
        })
        .catch(() => {
            list.innerHTML = "";
            feedback.textContent = "Could not load documents. Please try again.";
        });
}

function renderProgrammeDocuments(documents) {
    const list = document.getElementById("programme-documents-list");
    list.innerHTML = "";

    if (!documents.length) {
        list.innerHTML = '<div class="col-12"><span class="sub-text text-14">No document types are available.</span></div>';
        return;
    }

    documents.forEach((doc) => {
        const col = document.createElement("div");
        col.className = "col-12 col-md-6";

        const box = document.createElement("div");
        box.className = "form-check bl-solid p-3 d-flex align-items-start gap-2";

        const input = document.createElement("input");
        input.type = "checkbox";
        input.className = "form-check-input mt-1 programme-doc-checkbox";
        input.id = "programme-doc-" + doc.type_id;
        input.value = doc.type_id;
        input.checked = !!doc.assigned;

        const label = document.createElement("label");
        label.className = "form-check-label w-100 text-14 main-text";
        label.htmlFor = input.id;
        label.textContent = doc.type_name; // textContent: names are never parsed as HTML

        box.appendChild(input);
        box.appendChild(label);
        col.appendChild(box);
        list.appendChild(col);
    });
}

// Row action: Discontinue (was a confirm modal; now the status modal with reason)
const openDiscontinuConfirmModal = (update, intake, studentId) => {
    issOpenStatusModal(intake, update, {
        checked: [parseInt(studentId, 10)]
    });
}

// Row action: Activate / Deactivate (one student). Doesn't touch the bulk selection.
const updateIntakeStudentStatus = (update, intakeId, id) => {
    issOpenStatusModal(intakeId, update, {
        checked: [parseInt(id, 10)]
    });
}

// Bulk: uses the current selection (selectedUsers / unSelectedUsers).
const applySelectedIntakeStudentUpdate = (id, update) => {
    if (update == 0) {
        showToast("warning", "Please select the desired student status change option.");
        return;
    }
    issOpenStatusModal(id, update, {
        checked: typeof selectedUsers !== 'undefined' ? selectedUsers : [],
        unchecked: typeof unSelectedUsers !== 'undefined' ? unSelectedUsers : [],
    });
}

// Bulk "Apply" button next to the "- Update Students -" selector
function applyIntakeHasStudentsUpdate(id) {
    applySelectedIntakeStudentUpdate(id, $("#update-selector").val());
}