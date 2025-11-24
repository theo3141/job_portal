<?php
// employer/_post_job_modal.php
// This file is intended to be included by other PHP pages.

$job_type_options_for_modal = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
$experience_level_options_for_modal = ["Entry Level", "Associate", "Mid-Senior level", "Director", "Executive"];
$education_level_options_for_modal = ["High School Diploma", "Vocational Training", "Associate Degree", "Bachelor's Degree", "Master's Degree", "Doctorate", "Not Required"];
?>

<!-- The Modal for Posting a Job -->
<div id="postJobModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-briefcase"></i> Post a New Job</h2>
            <span class="close-button post-job-modal-close">×</span>
        </div>

        <form id="postJobModalForm" method="POST" action="post_job.php">
            <input type="hidden" name="source" value="modal">
            <input type="hidden" name="return_url" id="postJobModalReturnUrl" value="">

            <div class="form-group">
                <label for="modal_title">Job Title <span style="color:red;">*</span></label>
                <input type="text" id="modal_title" name="title" placeholder="e.g., Software Engineer" required>
            </div>
            <div class="form-group">
                <label for="modal_description">Job Description <span style="color:red;">*</span></label>
                <textarea id="modal_description" name="description" placeholder="Detailed responsibilities, company culture, etc." required></textarea>
            </div>
            <div class="form-group">
                <label for="modal_location">Location <span style="color:red;">*</span></label>
                <input type="text" id="modal_location" name="location" placeholder="e.g., City, State or 'Remote'" required>
            </div>
            <div class="form-group">
                <label for="modal_salary_modal_form">Salary (PHP) <span style="color:red;">*</span></label>
                <input type="number" id="modal_salary_modal_form" name="salary" placeholder="e.g., 50000" required step="0.01" min="1">
            </div>
            <hr style="margin: 20px 0;">
            <div class="form-group">
                <label for="modal_job_type">Job Type</label>
                <select id="modal_job_type" name="job_type">
                    <option value="">-- Select Job Type --</option>
                    <?php foreach ($job_type_options_for_modal as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="modal_experience_level">Experience Level</label>
                <select id="modal_experience_level" name="experience_level">
                    <option value="">-- Select Experience Level --</option>
                    <?php foreach ($experience_level_options_for_modal as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="modal_education_level">Education Level</label>
                <select id="modal_education_level" name="education_level">
                    <option value="">-- Select Education Level --</option>
                    <?php foreach ($education_level_options_for_modal as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="modal_skills_required">Skills Required</label>
                <textarea id="modal_skills_required" name="skills_required" placeholder="e.g., Java, Python, Project Management (comma-separated)"></textarea>
                <p class="small-text">Comma-separated list of skills.</p>
            </div>
            <button type="submit" class="btn-submit-modal"><i class="fas fa-paper-plane"></i> Post Job</button>
        </form>
    </div>
</div>

<style>
    /* --- MODAL STYLING --- */
    .modal { /* General modal styling for backdrop and positioning */
        display: none;
        position: fixed;
        z-index: 1000; /* High z-index to be on top */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto; /* Enable scroll if content is too long */
        background-color: rgba(0,0,0,0.5); /* Dimmed background */
        padding-top: 30px; /* Space from top */
    }

    /* Modal Content Box for Post Job Modal */
    #postJobModal .modal-content {
        background-color: #fff;
        margin: 3% auto; /* Centered with space around */
        padding: 25px 30px;
        border: 1px solid #bbb;
        width: 90%;
        max-width: 700px; /* Max width of the modal */
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        position: relative;
        overflow-y: auto; /* Scroll inside modal content if needed */
        max-height: 90vh; /* Max height relative to viewport */
    }

    #postJobModal .modal-header {
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    #postJobModal .modal-header h2 {
        margin: 0;
        color: #1D3557; /* Theme color */
        font-size: 1.7em;
        text-align: left;
    }

    #postJobModal .close-button.post-job-modal-close {
        color: #888;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        padding: 0 5px;
        line-height: 1;
    }
    #postJobModal .close-button.post-job-modal-close:hover,
    #postJobModal .close-button.post-job-modal-close:focus {
        color: #333;
        text-decoration: none;
    }

    /* Form elements within the Post Job Modal */
    #postJobModal .form-group {
        margin-bottom: 18px;
    }

    #postJobModal label {
        font-weight: 600;
        margin-bottom: 6px;
        color: #333;
        display: block;
        font-size: 0.95em;
    }

    #postJobModal input[type="text"],
    #postJobModal input[type="number"],
    #postJobModal textarea,
    #postJobModal select {
        padding: 12px 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        width: 100%;
        font-size: 1em;
        box-sizing: border-box; /* Important for width 100% and padding */
        transition: border-color 0.3s, box-shadow 0.3s;
        font-family: 'Poppins', sans-serif;
    }

    #postJobModal textarea {
        min-height: 100px;
        resize: vertical;
    }

    #postJobModal input:focus,
    #postJobModal textarea:focus,
    #postJobModal select:focus {
        outline: none;
        border-color: #457B9D; /* Theme color for focus */
        box-shadow: 0 0 0 0.2rem rgba(69, 123, 157, 0.25); /* Bootstrap-like focus glow */
    }

    #postJobModal select {
        cursor: pointer;
        background-color: #f8f9fa; /* Light background for select */
    }

    #postJobModal .btn-submit-modal {
        background-color: #457B9D; /* Theme button color */
        color: #fff;
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-size: 1.1em;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        margin-top: 15px;
        font-weight: 500;
        width: 100%; /* Full width button */
    }

    #postJobModal .btn-submit-modal:hover {
        background-color: #1D3557; /* Darker theme color on hover */
        transform: translateY(-2px);
    }

    #postJobModal .small-text {
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 5px; /* Space above */
        margin-bottom: 10px; /* Space below before next element */
    }
</style>