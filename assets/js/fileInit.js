const initializeIntakes = () => {
    const IntakePrefixConfig = {
        title: 'What is an Intake Prefix?',
        subtitle: 'The prefix part used before the sequence number in Student ID generation.',
        sections: [{
            title: 'Example Student ID: OC/2026/5B/PC100',
            items: [
                {
                    bullet: '•',
                    text: 'OC/2026/5B/PC = Intake Prefix ← this field',
                    highlight: true
                }
            ]
        }],
        warning: 'This prefix is used as the base format for all Student IDs in this intake.'
    };

    const StudentIdSequenceConfig = {
        title: 'What is Sequence Number?',
        subtitle: 'The numeric part appended after the intake prefix.',
        sections: [{
            title: 'Example Student ID: OC/2026/5B/PC100',
            items: [
                {
                    bullet: '•',
                    text: 'OC/2026/5B/PC = Intake prefix'
                },
                {
                    bullet: '•',
                    text: '100 = Sequence number ← this field',
                    highlight: true
                }
            ]
        }],
        warning: 'This number defines the starting point for Student ID generation. Example: if set to 100, the first student will receive OC/2026/5B/PC100.'
    };

    InfoTooltip.create('intake-prefix-info', IntakePrefixConfig);
    InfoTooltip.create('edit-intake-prefix-info', IntakePrefixConfig);

    InfoTooltip.create('student-id-sequence-info', StudentIdSequenceConfig);
    InfoTooltip.create('edit-student-id-sequence-info', StudentIdSequenceConfig);

    // Append to DOM
    $('#intake-prefix-info-wrapper').append(InfoTooltip.getElement('intake-prefix-info'));
    $('#edit-intake-prefix-info-wrapper').append(InfoTooltip.getElement('edit-intake-prefix-info'));

    $('#student-id-sequence-info-wrapper').append(InfoTooltip.getElement('student-id-sequence-info'));
    $('#edit-student-id-sequence-info-wrapper').append(InfoTooltip.getElement('edit-student-id-sequence-info'));
}

const initializeAddStudent = () => {
    const firstNameInput = document.getElementById("first-name");
    const lastNameInput = document.getElementById("last-name");
    const fullNameInput = document.getElementById("full-name");
    const initialsInput = document.getElementById("name-with-initials");

    function updateNames() {

        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();

        if (firstName || lastName) {
            fullNameInput.value = (firstName + " " + lastName).trim();
        } else {
            fullNameInput.value = "";
        }

        if (firstName && lastName) {

            const parts = firstName.split(/\s+/);

            const initials = parts
                .filter(p => p.length > 0)
                .map(p => p.charAt(0).toUpperCase() + ".")
                .join(" ");
            initialsInput.value = initials + " " + lastName;
        } else {
            initialsInput.value = "";
        }
    }

    // Trigger on typing
    firstNameInput.addEventListener("input", updateNames);
    lastNameInput.addEventListener("input", updateNames);

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


const initializeAddPreRecordingLectures = () => {
    const RecordingLinkConfig = {
        title: 'What is Recording Link?',
        subtitle: 'The video recording URL used to access lecture recordings.',
        sections: [{
            title: 'Valid Recording Link Format',
            items: [
                {
                    bullet: '•',
                    text: 'Only Bunny.net video links are allowed.'
                },
                {
                    bullet: '•',
                    text: 'Example: https://iframe.mediadelivery.net/embed/12345/abcdef'
                },
                {
                    bullet: '•',
                    text: 'The system validates the link before saving.',
                    highlight: true
                }
            ]
        }],
        warning: 'Recording link must be a valid Bunny.net URL. Links from other platforms will be rejected.'
    };

    InfoTooltip.create('link-prefix-info', RecordingLinkConfig);
    $('#link-info-wrapper').append(InfoTooltip.getElement('link-prefix-info'));

}

const initializeUploadLectureRecording = () => {
    const RecordingLinkConfig = {
        title: 'What is Recording Link?',
        subtitle: 'Paste the Bunny Stream embed iframe code for the lecture recording.',
        sections: [{
            title: 'Valid Recording Link Format',
            items: [
                {
                    bullet: '•',
                    text: 'Only Bunny Stream embed iframe codes are allowed.'
                },
                {
                    bullet: '•',
                    text: 'Example: <iframe src="https://iframe.mediadelivery.net/embed/123456/video-id"></iframe>'
                },
                {
                    bullet: '•',
                    text: 'The system validates the iframe source before saving.',
                    highlight: true
                }
            ]
        }],
        warning: 'Paste the complete Bunny Stream iframe embed code. Direct video URLs and links from other platforms are not allowed.'
    };

    InfoTooltip.create('link-prefix-info', RecordingLinkConfig);
    $('#link-info-wrapper').append(InfoTooltip.getElement('link-prefix-info'));
}