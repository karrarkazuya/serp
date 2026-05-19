# -*- coding: utf-8 -*-

from . import dashboard
from . import groups
from . import users
from . import managers
from . import departments
from . import extra_models

from .extra_models import product_line

from .reports import report_mixin
from .reports import report_activity
from .reports import report_procedure_performance
from .reports import report_ticket_performance
from .reports import report_task_performance

from .tickets import templates
from .tickets import templates_inputs
from .tickets import templates_inputs_subs
from .tickets import tickets
from .tickets import tickets_inputs
from .tickets import tickets_inputs_subs

from .procedures import templates
from .procedures import templates_tasks
from .procedures import templates_tasks_paths
from .procedures import templates_inputs
from .procedures import templates_inputs_subs

from .procedures import procedures
from .procedures import tasks
from .procedures import tasks_paths
from .procedures import tasks_procedure_lines
from .procedures import start_wizard
from .procedures import task_return_wizard
from .procedures import task_return_to_wizard
from .procedures import tasks_read
from .procedures import tasks_inputs
from .procedures import tasks_inputs_subs

from .overrides import res_partner
from .overrides import res_users
from .overrides import mail_bot
