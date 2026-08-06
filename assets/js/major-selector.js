document.addEventListener('DOMContentLoaded', () => {
    const facultySelect = document.getElementById('faculty_id');
    const majorSelect = document.getElementById('major_id');
    const majorsDataElement = document.getElementById('majorsData');

    if (!facultySelect || !majorSelect || !majorsDataElement) {
        return;
    }

    let majors = [];

    try {
        majors = JSON.parse(majorsDataElement.textContent);
    } catch (error) {
        console.error('Could not load majors data.', error);
        return;
    }

    const initialMajorId = majorSelect.dataset.selectedMajor ?? '';

    function renderMajors(selectedMajorId = '') {
        const facultyId = Number(facultySelect.value);

        majorSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = facultyId
            ? 'Select Major'
            : 'Select Faculty First';

        majorSelect.appendChild(defaultOption);

        if (!facultyId) {
            majorSelect.disabled = true;
            return;
        }

        const filteredMajors = majors.filter(
            (major) => Number(major.faculty_id) === facultyId
        );

        filteredMajors.forEach((major) => {
            const option = document.createElement('option');

            option.value = major.id;
            option.textContent = major.name;

            if (String(major.id) === String(selectedMajorId)) {
                option.selected = true;
            }

            majorSelect.appendChild(option);
        });

        majorSelect.disabled = false;
    }

    renderMajors(initialMajorId);

    facultySelect.addEventListener('change', () => {
        renderMajors('');
    });
});