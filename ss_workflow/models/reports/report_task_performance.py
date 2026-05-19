# -*- coding: utf-8 -*-

from odoo import models


class WorkflowTaskPerformanceReport(models.Model):
    _name = 'ss.report.workflow_task_performance'
    _description = 'Workflow Task Performance Report'
    _inherit = 'ss.workflow.report.mixin'
    _auto = False

    def _get_tasks(self, date_from, date_to, task_template_id=False):
        domain = [
            ('deleted', '=', False),
            ('create_date', '>=', date_from),
            ('create_date', '<=', date_to),
            ('task_id', '!=', False),
        ]
        if task_template_id:
            domain.append(('task_id', '=', task_template_id))
        return self.env['ssw.proc.tasks'].sudo().search(domain, order='task_id, id')

    def _get_summary_data(self, tasks):
        buckets = {}
        for task in tasks:
            task_template = task.task_id
            bucket = buckets.setdefault(task_template.id, {
                'task_template_id': task_template.id,
                'task_template_name': task_template.name or task.name or '',
                'started_count': 0,
                'completed_count': 0,
                'finish_total': 0.0,
                'sla_passed_total': 0.0,
                'department_stats': {},
            })
            bucket['started_count'] += 1
            if task.state == 'completed':
                bucket['completed_count'] += 1
                bucket['finish_total'] += task.resolve_duration or 0.0
                bucket['sla_passed_total'] += task.resolve_deadline_passed or 0.0

            if task.assigned_to_dep:
                stat = bucket['department_stats'].setdefault(task.assigned_to_dep.id, {
                    'name': task.assigned_to_dep.name or '',
                    'total_duration': 0.0,
                    'count': 0,
                })
                stat['total_duration'] += task.resolve_duration or 0.0
                stat['count'] += 1

        data = []
        for bucket in sorted(buckets.values(), key=lambda item: (item['task_template_name'] or '').lower()):
            completed_count = bucket['completed_count']
            avg_finish_num = (bucket['finish_total'] / completed_count) if completed_count else 0.0
            sla_passed_num = bucket['sla_passed_total']

            ranked_departments = []
            for stat in bucket['department_stats'].values():
                avg_time = (stat['total_duration'] / stat['count']) if stat['count'] else 0.0
                ranked_departments.append({
                    'name': stat['name'],
                    'avg_time_num': avg_time,
                    'avg_time': self._format_hours(avg_time),
                })
            ranked_departments.sort(key=lambda item: (-item['avg_time_num'], item['name']))

            top_1 = ranked_departments[0] if ranked_departments else {'name': '', 'avg_time_num': 0.0, 'avg_time': ''}
            top_2 = ranked_departments[1] if len(ranked_departments) > 1 else {'name': '', 'avg_time_num': 0.0, 'avg_time': ''}

            data.append({
                'task_template_id': bucket['task_template_id'],
                'task_template_name': bucket['task_template_name'],
                'started_count_num': bucket['started_count'],
                'started_count': str(bucket['started_count']),
                'completed_count_num': completed_count,
                'completed_count': str(completed_count),
                'avg_finish_num': avg_finish_num,
                'avg_finish': self._format_hours(avg_finish_num),
                'sla_passed_num': sla_passed_num,
                'sla_passed': self._format_hours(sla_passed_num),
                'longest_1_name': top_1['name'],
                'longest_1_time_num': top_1['avg_time_num'],
                'longest_1_time': top_1['avg_time'],
                'longest_2_name': top_2['name'],
                'longest_2_time_num': top_2['avg_time_num'],
                'longest_2_time': top_2['avg_time'],
                'lines': [],
                'lines_loaded': False,
            })
        return data

    def get_report(self, date_from=False, date_to=False):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        tasks = self._get_tasks(date_from, date_to)

        return {
            'report_name': 'Task Performance',
            'parameters': {
                'date_from': date_from.date(),
                'date_to': date_to.date(),
            },
            'data': self._get_summary_data(tasks),
        }

    def get_template_lines(self, task_template_id, date_from, date_to):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        tasks = self._get_tasks(date_from, date_to, task_template_id=task_template_id)

        result = []
        for task in tasks:
            result.append({
                'line_key': 'task-{0}'.format(task.id),
                'task_id': task.id,
                'task_name': task.name or '',
                'procedure_name': task.procedure_id.name or '',
                'assigned_department': task.assigned_to_dep.name or '',
                'assigned_user': task.assigned_to_user.name or '',
                'state': task.state or '',
                'finish': self._format_hours(task.resolve_duration or 0.0),
                'sla_passed': self._format_hours(task.resolve_deadline_passed or 0.0),
            })
        return result
