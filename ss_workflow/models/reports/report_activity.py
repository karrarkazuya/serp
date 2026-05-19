# -*- coding: utf-8 -*-

from odoo import models


class WorkflowActivityReport(models.Model):
    _name = 'ss.report.workflow_activity'
    _description = 'Workflow Activity Report'
    _inherit = 'ss.workflow.report.mixin'
    _auto = False

    def _get_user_lines(self, workflow_user, date_from, date_to):
        partner = workflow_user.partner_id
        user = workflow_user.user_id
        lines = []

        opened_tickets = self.env['ssw.tickets'].sudo().search([
            ('deleted', '=', False),
            ('created_by', '=', partner.id),
            ('create_date', '>=', date_from),
            ('create_date', '<=', date_to),
        ], order='create_date desc, id desc')
        for ticket in opened_tickets:
            lines.append({
                'line_key': 'ticket-open-{0}'.format(ticket.id),
                'model': 'ssw.tickets',
                'res_id': ticket.id,
                'record_type': 'Ticket',
                'record_name': ticket.name or '',
                'event': 'Opened',
                'event_date': ticket.create_date,
                'duration': '',
            })

        closed_tickets = self.env['ssw.tickets'].sudo().search([
            ('deleted', '=', False),
            ('write_uid', '=', user.id),
            ('state', 'in', ['completed', 'closed']),
            ('write_date', '>=', date_from),
            ('write_date', '<=', date_to),
        ], order='write_date desc, id desc')
        for ticket in closed_tickets:
            lines.append({
                'line_key': 'ticket-close-{0}'.format(ticket.id),
                'model': 'ssw.tickets',
                'res_id': ticket.id,
                'record_type': 'Ticket',
                'record_name': ticket.name or '',
                'event': 'Closed',
                'event_date': ticket.write_date,
                'duration': self._format_hours(ticket.resolve_duration or 0.0),
            })

        completed_tasks = self.env['ssw.proc.tasks'].sudo().search([
            ('deleted', '=', False),
            ('write_uid', '=', user.id),
            ('state', '=', 'completed'),
            ('write_date', '>=', date_from),
            ('write_date', '<=', date_to),
        ], order='write_date desc, id desc')
        for task in completed_tasks:
            lines.append({
                'line_key': 'task-complete-{0}'.format(task.id),
                'model': 'ssw.proc.tasks',
                'res_id': task.id,
                'record_type': 'Task',
                'record_name': task.name or '',
                'event': 'Completed',
                'event_date': task.write_date,
                'duration': self._format_hours(task.resolve_duration or 0.0),
            })

        started_procedures = self.env['ssw.procedures'].sudo().search([
            ('deleted', '=', False),
            ('created_by', '=', partner.id),
            ('create_date', '>=', date_from),
            ('create_date', '<=', date_to),
        ], order='create_date desc, id desc')
        for procedure in started_procedures:
            lines.append({
                'line_key': 'procedure-start-{0}'.format(procedure.id),
                'model': 'ssw.procedures',
                'res_id': procedure.id,
                'record_type': 'Procedure',
                'record_name': procedure.name or '',
                'event': 'Started',
                'event_date': procedure.create_date,
                'duration': '',
            })

        lines.sort(key=lambda item: str(item['event_date'] or ''), reverse=True)
        for line in lines:
            line['event_date'] = line['event_date'] or ''
        return lines

    def get_report(self, date_from=False, date_to=False, department_id=False):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        department_id = int(department_id) if department_id else self._get_default_department_id()

        departments = self.env['ssw.departments'].sudo().search([
            ('deleted', '=', False),
        ], order='name')
        departments_data = [{'id': dep.id, 'title': dep.name} for dep in departments]

        users = self.env['ssw.users'].sudo().search([
            ('deleted', '=', False),
            ('default_department', '=', department_id),
            ('partner_id', '!=', False),
        ], order='name')
        partner_ids = users.mapped('partner_id').ids
        user_ids = users.mapped('user_id').ids

        opened_map = {}
        closed_map = {}
        response_map = {}
        tasks_completed_map = {}
        task_duration_map = {}
        procedures_started_map = {}

        if partner_ids:
            opened_rows = self.env['ssw.tickets'].sudo().read_group(
                [
                    ('deleted', '=', False),
                    ('created_by', 'in', partner_ids),
                    ('create_date', '>=', date_from),
                    ('create_date', '<=', date_to),
                ],
                ['created_by'],
                ['created_by'],
                lazy=False,
            )
            opened_map = {
                row['created_by'][0]: row['__count']
                for row in opened_rows if row.get('created_by')
            }

            closed_rows = self.env['ssw.tickets'].sudo().read_group(
                [
                    ('deleted', '=', False),
                    ('write_uid', 'in', user_ids),
                    ('state', 'in', ['completed', 'closed']),
                    ('write_date', '>=', date_from),
                    ('write_date', '<=', date_to),
                ],
                ['assigned_to_user'],
                ['assigned_to_user'],
                lazy=False,
            )
            closed_map = {
                row['assigned_to_user'][0]: row['__count']
                for row in closed_rows if row.get('assigned_to_user')
            }

            response_rows = self.env['ssw.tickets.durations'].sudo().read_group(
                [
                    ('write_uid', 'in', user_ids),
                    ('create_date', '>=', date_from),
                    ('create_date', '<=', date_to),
                ],
                ['user_id', 'duration:avg'],
                ['user_id'],
                lazy=False,
            )
            response_map = {
                row['user_id'][0]: row.get('duration_avg') or 0.0
                for row in response_rows if row.get('user_id')
            }

            tasks_completed_rows = self.env['ssw.proc.tasks'].sudo().read_group(
                [
                    ('deleted', '=', False),
                    ('write_uid', 'in', user_ids),
                    ('state', '=', 'completed'),
                    ('write_date', '>=', date_from),
                    ('write_date', '<=', date_to),
                ],
                ['assigned_to_user'],
                ['assigned_to_user'],
                lazy=False,
            )
            tasks_completed_map = {
                row['assigned_to_user'][0]: row['__count']
                for row in tasks_completed_rows if row.get('assigned_to_user')
            }

            task_metric_rows = self.env['ssw.proc.tasks'].sudo().read_group(
                [
                    ('deleted', '=', False),
                    ('write_uid', 'in', user_ids),
                    ('state', '=', 'completed'),
                    ('write_date', '>=', date_from),
                    ('write_date', '<=', date_to),
                ],
                ['assigned_to_user', 'resolve_duration:avg'],
                ['assigned_to_user'],
                lazy=False,
            )
            task_duration_map = {
                row['assigned_to_user'][0]: row.get('resolve_duration_avg') or 0.0
                for row in task_metric_rows if row.get('assigned_to_user')
            }

            procedures_started_rows = self.env['ssw.procedures'].sudo().read_group(
                [
                    ('deleted', '=', False),
                    ('created_by', 'in', partner_ids),
                    ('create_date', '>=', date_from),
                    ('create_date', '<=', date_to),
                ],
                ['created_by'],
                ['created_by'],
                lazy=False,
            )
            procedures_started_map = {
                row['created_by'][0]: row['__count']
                for row in procedures_started_rows if row.get('created_by')
            }


        data = []
        for workflow_user in users:
            partner = workflow_user.partner_id
            partner_id = partner.id

            response_time_num = response_map.get(partner_id, 0.0)
            task_duration_num = task_duration_map.get(partner_id, 0.0)
            data.append({
                'user_id': workflow_user.id,
                'partner_id': partner_id,
                'user_name': workflow_user.name or partner.name or '',
                'tickets_opened_num': opened_map.get(partner_id, 0),
                'tickets_opened': str(opened_map.get(partner_id, 0)),
                'tickets_closed_num': closed_map.get(partner_id, 0),
                'tickets_closed': str(closed_map.get(partner_id, 0)),
                'response_time_num': response_time_num,
                'response_time': self._format_hours(response_time_num),
                'tasks_completed_num': tasks_completed_map.get(partner_id, 0),
                'tasks_completed': str(tasks_completed_map.get(partner_id, 0)),
                'task_duration_num': task_duration_num,
                'task_duration': self._format_hours(task_duration_num),
                'procedures_started_num': procedures_started_map.get(partner_id, 0),
                'procedures_started': str(procedures_started_map.get(partner_id, 0)),
                'lines': [],
                'lines_loaded': False,
            })

        return {
            'report_name': 'Workflow Activity',
            'parameters': {
                'date_from': date_from.date(),
                'date_to': date_to.date(),
                'department_id': department_id,
                'department_name': departments.filtered(lambda dep: dep.id == department_id).name or '',
            },
            'departments_data': departments_data,
            'data': data,
        }

    def get_user_lines(self, workflow_user_id, date_from, date_to):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        workflow_user = self.env['ssw.users'].sudo().browse(int(workflow_user_id))
        if not workflow_user.exists():
            return []
        return self._get_user_lines(workflow_user, date_from, date_to)
