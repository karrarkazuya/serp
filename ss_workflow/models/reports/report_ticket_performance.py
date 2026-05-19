# -*- coding: utf-8 -*-

from odoo import models


class WorkflowTicketPerformanceReport(models.Model):
    _name = 'ss.report.workflow_ticket_performance'
    _description = 'Workflow Ticket Performance Report'
    _inherit = 'ss.workflow.report.mixin'
    _auto = False

    def _get_tickets(self, date_from, date_to, template_id=False):
        domain = [
            ('deleted', '=', False),
            ('create_date', '>=', date_from),
            ('create_date', '<=', date_to),
            ('template_id', '!=', False),
        ]
        if template_id:
            domain.append(('template_id', '=', template_id))
        return self.env['ssw.tickets'].sudo().search(domain, order='template_id, id')

    def _get_summary_data(self, tickets):
        buckets = {}
        for ticket in tickets:
            template = ticket.template_id
            bucket = buckets.setdefault(template.id, {
                'template_id': template.id,
                'template_name': template.name or '',
                'opened_count': 0,
                'finished_count': 0,
                'finish_total': 0.0,
                'sla_passed_total': 0.0,
                'department_stats': {},
            })
            bucket['opened_count'] += 1
            if ticket.state == 'completed':
                bucket['finished_count'] += 1
                bucket['finish_total'] += ticket.resolve_duration or 0.0
                bucket['sla_passed_total'] += ticket.resolve_deadline_passed or 0.0

        durations = self.env['ssw.tickets.durations'].sudo().search([
            ('ticket_id', 'in', tickets.ids),
            ('department_id', '!=', False),
        ])

        for duration in durations:
            template_id = duration.ticket_id.template_id.id
            if template_id not in buckets:
                continue

            department_stats = buckets[template_id]['department_stats']
            stat = department_stats.setdefault(duration.department_id.id, {
                'name': duration.department_id.name or '',
                'total_duration': 0.0,
                'count': 0,
            })
            stat['total_duration'] += duration.duration or 0.0
            stat['count'] += 1

        data = []
        for bucket in sorted(buckets.values(), key=lambda item: (item['template_name'] or '').lower()):
            finished_count = bucket['finished_count']
            avg_finish_num = (bucket['finish_total'] / finished_count) if finished_count else 0.0
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
                'template_id': bucket['template_id'],
                'template_name': bucket['template_name'],
                'opened_count_num': bucket['opened_count'],
                'opened_count': str(bucket['opened_count']),
                'finished_count_num': finished_count,
                'finished_count': str(finished_count),
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
        tickets = self._get_tickets(date_from, date_to)

        return {
            'report_name': 'Ticket Performance',
            'parameters': {
                'date_from': date_from.date(),
                'date_to': date_to.date(),
            },
            'data': self._get_summary_data(tickets),
        }

    def get_template_lines(self, template_id, date_from, date_to):
        date_from, date_to = self._normalize_dates(date_from, date_to)
        tickets = self._get_tickets(date_from, date_to, template_id=template_id)

        result = []
        for ticket in tickets:
            durations = ticket.durations.filtered(lambda line: line.department_id)
            ranked_departments = sorted(
                durations,
                key=lambda line: (-(line.duration or 0.0), line.department_id.name or ''),
            )
            top_department = ranked_departments[0] if ranked_departments else False
            result.append({
                'line_key': 'ticket-{0}'.format(ticket.id),
                'ticket_id': ticket.id,
                'ticket_name': ticket.name or '',
                'assigned_department': ticket.assigned_to_dep.name or '',
                'assigned_user': ticket.assigned_to_user.name or '',
                'state': ticket.state or '',
                'finish': self._format_hours(ticket.resolve_duration or 0.0),
                'sla_passed': self._format_hours(ticket.resolve_deadline_passed or 0.0),
                'longest_department_name': top_department.department_id.name if top_department else '',
                'longest_department_time': self._format_hours(top_department.duration or 0.0) if top_department else '',
            })
        return result
