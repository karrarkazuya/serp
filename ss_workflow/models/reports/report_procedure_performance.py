# -*- coding: utf-8 -*-

from odoo import models


class WorkflowProcedurePerformanceReport(models.Model):
    _name = 'ss.report.workflow_procedure_performance'
    _description = 'Workflow Procedure Performance Report'
    _inherit = 'ss.workflow.report.mixin'
    _auto = False

    def _get_procedures(self, date_from, date_to, template_id=False):
        domain = [
            ('deleted', '=', False),
            ('create_date', '>=', date_from),
            ('create_date', '<=', date_to),
            ('template_id', '!=', False),
        ]
        if template_id:
            domain.append(('template_id', '=', template_id))
        return self.env['ssw.procedures'].sudo().search(domain, order='template_id, id')

    def _get_summary_data(self, procedures):
        buckets = {}
        for procedure in procedures:
            template = procedure.template_id
            bucket = buckets.setdefault(template.id, {
                'template_id': template.id,
                'template_name': template.name or '',
                'started_count': 0,
                'completed_count': 0,
                'finish_total': 0.0,
                'sla_passed_total': 0.0,
                'task_stats': {},
            })
            bucket['started_count'] += 1
            if procedure.state == 'completed':
                bucket['completed_count'] += 1
                bucket['finish_total'] += procedure.resolve_duration or 0.0
                bucket['sla_passed_total'] += procedure.resolve_deadline_passed or 0.0

        tasks = self.env['ssw.proc.tasks'].sudo().search([
            ('deleted', '=', False),
            ('procedure_id', 'in', procedures.ids),
            ('task_id', '!=', False),
            ('state', '=', 'completed'),
        ])

        for task in tasks:
            template_id = task.procedure_id.template_id.id
            if template_id not in buckets:
                continue

            task_stats = buckets[template_id]['task_stats']
            stat = task_stats.setdefault(task.task_id.id, {
                'name': task.task_id.name or task.name or '',
                'total_duration': 0.0,
                'count': 0,
            })
            stat['total_duration'] += task.resolve_duration or 0.0
            stat['count'] += 1

        data = []
        for bucket in sorted(buckets.values(), key=lambda item: (item['template_name'] or '').lower()):
            completed_count = bucket['completed_count']
            avg_finish_num = (bucket['finish_total'] / completed_count) if completed_count else 0.0
            sla_passed_num = bucket['sla_passed_total']

            ranked_tasks = []
            for stat in bucket['task_stats'].values():
                avg_time = (stat['total_duration'] / stat['count']) if stat['count'] else 0.0
                ranked_tasks.append({
                    'name': stat['name'],
                    'avg_time_num': avg_time,
                    'avg_time': self._format_hours(avg_time),
                })
            ranked_tasks.sort(key=lambda item: (-item['avg_time_num'], item['name']))

            top_1 = ranked_tasks[0] if ranked_tasks else {'name': '', 'avg_time_num': 0.0, 'avg_time': ''}
            top_2 = ranked_tasks[1] if len(ranked_tasks) > 1 else {'name': '', 'avg_time_num': 0.0, 'avg_time': ''}

            data.append({
                'template_id': bucket['template_id'],
                'template_name': bucket['template_name'],
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
        procedures = self._get_procedures(date_from, date_to)

        return {
            'report_name': 'Procedure Performance',
            'parameters': {
                'date_from': date_from.date(),
                'date_to': date_to.date(),
            },
            'data': self._get_summary_data(procedures),
        }

    def get_template_lines(self, template_id, date_from, date_to):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        procedures = self._get_procedures(date_from, date_to, template_id=template_id)

        result = []
        for procedure in procedures:
            completed_tasks = procedure.tasks.filtered(lambda task: not task.deleted and task.state == 'completed')
            ranked_tasks = sorted(
                completed_tasks,
                key=lambda task: (-(task.resolve_duration or 0.0), task.name or ''),
            )
            top_task = ranked_tasks[0] if ranked_tasks else False
            result.append({
                'line_key': 'proc-{0}'.format(procedure.id),
                'procedure_id': procedure.id,
                'procedure_name': procedure.name or '',
                'created_by': procedure.created_by.name or '',
                'state': procedure.state or '',
                'finish': self._format_hours(procedure.resolve_duration or 0.0),
                'sla_passed': self._format_hours(procedure.resolve_deadline_passed or 0.0),
                'longest_task_name': top_task.name if top_task else '',
                'longest_task_time': self._format_hours(top_task.resolve_duration or 0.0) if top_task else '',
            })
        return result
