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
 * Library functions for block courseexpirynotice
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

        // Number of courses to display per page.
        $perpage = 5;

        // Get current page.
        // Moodle pagination starts from 0.
        $page = optional_param('courseexpirynotice_page', 0, PARAM_INT);

        if ($page < 0) {
            $page = 0;
        }

        /*
         * Count total enrolled courses.
         */
        $countsql = "
            SELECT COUNT(DISTINCT c.id)
              FROM {user_enrolments} ue
              JOIN {enrol} e
                ON ue.enrolid = e.id
              JOIN {course} c
                ON e.courseid = c.id
             WHERE ue.userid = :userid
        ";

        $totalcourses = $DB->count_records_sql(
            $countsql,
            [
                'userid' => $USER->id,
            ]
        );

        /*
         * Calculate total pages.
         */
        $totalpages = (int) ceil($totalcourses / $perpage);

        /*
         * Make sure the requested page is not
         * beyond the last available page.
         */
        if ($totalpages > 0 && $page >= $totalpages) {
            $page = $totalpages - 1;
        }

        /*
         * Calculate database offset.
         *
         * Page 0 = courses 1-5.
         * Page 1 = courses 6-10.
         * Page 2 = courses 11-15.
         */
        $offset = $page * $perpage;

        /*
         * Get courses for the current page.
         */
        $sql = "
            SELECT DISTINCT
                c.id,
                c.fullname,
                ue.timeend
              FROM {user_enrolments} ue
              JOIN {enrol} e
                ON ue.enrolid = e.id
              JOIN {course} c
                ON e.courseid = c.id
             WHERE ue.userid = :userid
          ORDER BY c.fullname ASC
        ";

        $courses = $DB->get_records_sql(
            $sql,
            [
                'userid' => $USER->id,
            ],
            $offset,
            $perpage
        );

        /*
         * Display courses.
         */
        foreach ($courses as $course) {

            // Course name.
            $this->content->text .= html_writer::tag(
                'strong',
                format_string($course->fullname)
            );

            $this->content->text .= html_writer::empty_tag('br');

            /*
             * Check whether the enrolment has an expiry date.
             */
            if (empty($course->timeend)) {

                $this->content->text .= get_string(
                    'noexpiry',
                    'block_courseexpirynotice'
                );

            } else {

                // Format expiry date.
                $date = userdate($course->timeend);

                // Calculate remaining time.
                $secondsremaining = $course->timeend - time();

                // Convert remaining seconds to days.
                $daysremaining = (int) ceil(
                    $secondsremaining / DAYSECS
                );

                // Display expiry date.
                $this->content->text .= get_string(
                    'expireson',
                    'block_courseexpirynotice'
                ) . ': ' . $date;

                $this->content->text .= html_writer::empty_tag('br');

                // Display remaining days.
                if ($daysremaining > 0) {

                    $this->content->text .=
                        'Days remaining: ' . $daysremaining;

                } else if ($daysremaining == 0) {

                    $this->content->text .= 'Expires today';

                } else {

                    $this->content->text .= 'Expired';
                }
            }

            // Separator between courses.
            $this->content->text .= html_writer::empty_tag('hr');
        }

        /*
         * Display Moodle's default pagination.
         */
        if ($totalpages > 1) {

            /*
             * Create the base URL using the block page.
             *
             * Do not use global $PAGE.
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


