# -*- coding: utf-8 -*-

from datetime import datetime

from odoo import fields, models


class WorkflowReportMixin(models.AbstractModel):
    _name = 'ss.workflow.report.mixin'
    _description = 'Workflow Report Helper'

    def _normalize_dates(self, date_from=False, date_to=False):
        if date_from:
            date_from = fields.Datetime.to_datetime(date_from + ' 00:00:00')
        if date_to:
            date_to = fields.Datetime.to_datetime(date_to + ' 23:59:59')

        if not date_from and not date_to:
            year = datetime.now().year
            date_from = datetime(year, 1, 1, 0, 0, 0)
            date_to = datetime(year, 12, 31, 23, 59, 59)
        elif not date_from:
            date_from = datetime(date_to.year, 1, 1, 0, 0, 0)
        elif not date_to:
            date_to = datetime(date_from.year, 12, 31, 23, 59, 59)

        return date_from, date_to

    def _format_hours(self, value):
        return format(value or 0.0, ",.2f")

    def _get_default_department_id(self):
        workflow_user = self.env['ssw.users'].sudo().search([
            ('user_id', '=', self.env.user.id),
            ('deleted', '=', False),
        ], limit=1)
        if workflow_user and workflow_user.default_department:
            return workflow_user.default_department.id

        department = self.env['ssw.departments'].sudo().search([
            ('deleted', '=', False),
        ], limit=1, order='name')
        return department.id or False
