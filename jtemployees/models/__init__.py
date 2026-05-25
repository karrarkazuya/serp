# -*- coding: utf-8 -*-

from . import hr_employee
from . import dashboard
from . import employee_images
from . import employee_grades
from . import employee_grades_groups
from . import employee_certificates

from .points import employee_points
from .points import employee_points_templates
from .points import employee_points_inputs
from .points import employee_points_groups
from .points import employee_points_rewards

from .schedules import passed_days
from .schedules import planned_days
from .schedules import repeate_schedules

#from . import employee_fingerprint # to be removed

from . import sub_employee
from .fingerprint import fingerprint_devices
from .fingerprint import fingerprint_log
from .fingerprint import fingerprint_lograw

from .payroll import payrolls
from .payroll import payrolls_details
from .payroll import payrolls_details_subs
from .payroll import payrolls_slips
from .payroll import payrolls_slips_warnings

from .extra import extra_allocations
from .extra import extra_shortages
from .extra import extra_bounces

from . import employee_requests
from . import employee_holiday
from . import employee_wall

from .location import location_areas
from .location import hr_attendance

from .evaluation import evaluation_objectives
from .evaluation import evaluation_groups
from .evaluation import evaluation_values

from .resource import resource_calendar

from .log import allocations

from .report import general_report
from .report import payroll_report
from .report import employee_select_wizard
from .report import payroll_select_wizard
from .report import absence

from . import mail_message


from . import res_company
from . import settings
