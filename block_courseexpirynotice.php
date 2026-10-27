<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block class for block_courseexpirynotice.
 *
 * @package    block_courseexpirynotice
 * @copyright  2026 Dynamic pixel multimedia solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Course expiry block class.
 */
class block_courseexpirynotice extends block_base {

    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_courseexpirynotice');
    }

    /**
     * Define where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'course-view' => false,
            'site' => false,
        ];
    }

    /**
     * Get block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $DB, $USER;

        // Return existing content if already generated.
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Number of courses to display per page.
        $perpage = 5;

        // Get the requested page. Moodle pagination starts from 0.
        $page = optional_param('courseexpirynotice_page', 0, PARAM_INT);

        if ($page < 0) {
            $page = 0;
        }

        /*
         * Get courses in which the current user has active enrolments.
         * The onlyactive argument excludes inactive enrolments and
         * disabled enrolment instances.
         */
        $usercourses = enrol_get_all_users_courses($USER->id, true);

        /*
         * Exclude hidden courses unless the user has permission
         * to view hidden courses.
         */
        $visiblecourses = [];

        foreach ($usercourses as $course) {
            $coursecontext = context_course::instance($course->id);

            if ($course->visible ||
                    has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                $visiblecourses[$course->id] = $course;
            }
        }

        /*
         * Sort courses alphabetically by course name using the
         * Moodle collator so accented characters sort correctly.
         */
        core_collator::asort_objects_by_property(
            $visiblecourses,
            'fullname',
            core_collator::SORT_STRING
        );

        /*
         * Count the courses that the user is allowed to see.
         */
        $totalcourses = count($visiblecourses);

        /*
         * Calculate total pages.
         */
        $totalpages = (int) ceil($totalcourses / $perpage);

        /*
         * Make sure the requested page is not beyond the last page.
         */
        if ($totalpages > 0 && $page >= $totalpages) {
            $page = $totalpages - 1;
        }

        // If there are no courses, use the first page.
        if ($totalpages === 0) {
            $page = 0;
        }

        /*
         * Calculate the offset and select the courses for this page.
         *
         * Page 0 = courses 1-5.
         * Page 1 = courses 6-10.
         * Page 2 = courses 11-15.
         */
        $offset = $page * $perpage;

        $pagecourses = array_slice(
            array_values($visiblecourses),
            $offset,
            $perpage
        );

        /*
         * Prepare the current page's courses.
         * Use the course ID as the array key to avoid duplicate entries.
         */
        $courses = [];

        foreach ($pagecourses as $course) {
            $course->timeend = 0;
            $courses[$course->id] = $course;
        }

        /*
         * Retrieve expiry information from active user enrolments
         * in enabled enrolment instances for the current page only.
         */
        if (!empty($courses)) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_keys($courses),
                SQL_PARAMS_NAMED,
                'courseid'
            );

            $sql = "
                SELECT e.courseid AS id,
                       CASE
                           WHEN MIN(ue.timeend) = 0 THEN 0
                           ELSE MAX(ue.timeend)
                       END AS timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND ue.status = :useractive
                   AND e.status = :enrolenabled
                   AND e.courseid $insql
              GROUP BY e.courseid
            ";

            $params = array_merge(
                [
                    'userid' => $USER->id,
                    'useractive' => ENROL_USER_ACTIVE,
                    'enrolenabled' => ENROL_INSTANCE_ENABLED,
                ],
                $inparams
            );

            $expiryrecords = $DB->get_records_sql($sql, $params);

            /*
             * Apply the expiry information to the matching course.
             * If any qualifying enrolment has no expiry date,
             * the course is treated as having no expiry date.
             * Otherwise, use the latest expiry date.
             */
            foreach ($expiryrecords as $record) {
                if (isset($courses[$record->id])) {
                    $courses[$record->id]->timeend = (int) $record->timeend;
                }
            }
        }

        /*
         * Display a message when the user has no visible enrolled courses.
         */
        if (empty($courses)) {
            $this->content->text .= get_string(
                'nocourses',
                'block_courseexpirynotice'
            );
        }

        /*
         * Display courses and their expiry information.
         */
        foreach ($courses as $course) {

            $coursecontext = context_course::instance($course->id);

            // Display the course name, filtered in the course context.
            $this->content->text .= html_writer::tag(
                'strong',
                format_string($course->fullname, true, ['context' => $coursecontext])
            );

            $this->content->text .= html_writer::empty_tag('br');

            /*
             * Check whether the course has an expiry date.
             */
            if (empty($course->timeend)) {

                $this->content->text .= get_string(
                    'noexpiry',
                    'block_courseexpirynotice'
                );

            } else {

                // Format the expiry date.
                $date = userdate($course->timeend);

                // Calculate the remaining time.
                $secondsremaining = $course->timeend - time();

                // Convert the remaining seconds to days.
                $daysremaining = (int) ceil(
                    $secondsremaining / DAYSECS
                );

                // Display the expiry date.
                $this->content->text .= get_string(
                    'expireson',
                    'block_courseexpirynotice',
                    $date
                );

                $this->content->text .= html_writer::empty_tag('br');

                // Display the remaining days.
                if ($daysremaining > 0) {

                    $this->content->text .= get_string(
                        'daysremaining',
                        'block_courseexpirynotice',
                        $daysremaining
                    );

                } else if ($daysremaining === 0) {

                    $this->content->text .= get_string(
                        'expires_today',
                        'block_courseexpirynotice'
                    );

                } else {

                    $this->content->text .= get_string(
                        'expired',
                        'block_courseexpirynotice'
                    );
                }
            }

            // Add a separator between courses.
            $this->content->text .= html_writer::empty_tag('hr');
        }

        /*
         * Display Moodle's default pagination.
         */
        if ($totalpages > 1) {

            /*
             * Create the base URL using the block page.
             * Do not use the global $PAGE object.
             */
            $baseurl = new moodle_url($this->page->url);

            /*
             * Remove the current pagination parameter.
             */
            $baseurl->remove_params('courseexpirynotice_page');

            /*
             * Get the core renderer from the current page.
             */
            $renderer = $this->page->get_renderer('core');

            /*
             * Render Moodle's native paging bar.
             */
            $this->content->text .= $renderer->paging_bar(
                $totalcourses,
                $page,
                $perpage,
                $baseurl,
                'courseexpirynotice_page'
            );
        }

        return $this->content;
    }
}
