from odoo import http, fields
from odoo.http import request

class DashboardController(http.Controller):

    def _format_datetime(self, value):
        if not value:
            return "-"
        localized = fields.Datetime.context_timestamp(request.env.user, value)
        return localized.strftime("%Y-%m-%d %H:%M")

    def _action_url(self, model, action_id, menu_id):
        return f"/web#action={action_id}&model={model}&view_type=list&menu_id={menu_id}"

    def _record_url(self, model, record_id, action_id, menu_id):
        return f"/web#id={record_id}&model={model}&view_type=form&action={action_id}&menu_id={menu_id}"

    def _serialize_ticket(self, record, action_id, menu_id):
        return {
            'id': record.id,
            'name': record.name,
            'state_key': record.state,
            'state_label': dict(record._fields['state'].selection).get(record.state, record.state),
            'assigned_department': record.assigned_to_dep.name or "-",
            'assigned_user': record.assigned_to_user.name or "-",
            'deadline': self._format_datetime(record.resolve_deadline),
            'meta': record.template_id.name or "No template",
            'url': self._record_url('ssw.tickets', record.id, action_id, menu_id),
        }

    def _serialize_task(self, record, action_id, menu_id):
        return {
            'id': record.id,
            'name': record.name,
            'state_key': record.state,
            'state_label': dict(record._fields['state'].selection).get(record.state, record.state),
            'assigned_department': record.assigned_to_dep.name or "-",
            'assigned_user': record.assigned_to_user.name or "-",
            'deadline': self._format_datetime(record.resolve_deadline),
            'meta': record.procedure_id.name or "No procedure",
            'url': self._record_url('ssw.proc.tasks', record.id, action_id, menu_id),
        }

    @http.route('/ss_workflow/main_dashboard', auth='user')
    def get_dashboard_data(self):
        tickets_action = request.env.ref('ss_workflow.action_tickets_view')
        tasks_action = request.env.ref('ss_workflow.action_tasks')
        ticket_submit_action = request.env.ref('ss_workflow.tickets_action_templates_create')
        procedure_start_action = request.env.ref('ss_workflow.procedures_action_templates_create')
        tickets_menu = request.env.ref('ss_workflow.ssw_menu_action_window')
        tasks_menu = request.env.ref('ss_workflow.ssw_tasks_menu_action_window')
        procedures_menu = request.env.ref('ss_workflow.ssw_procedures_menu_action_window')

        user_partner = request.env.user.partner_id
        workflow_user = request.env['ssw.users'].sudo().search([
            ('user_id', '=', request.env.user.id),
            ('deleted', '=', False),
        ], limit=1)
        department_domain = [('id', '=', 0)]
        if workflow_user.default_department:
            department_domain = [('assigned_to_dep', '=', workflow_user.default_department.id)]

        ticket_model = request.env['ssw.tickets']
        task_model = request.env['ssw.proc.tasks']

        ticket_base_domain = [('deleted', '=', False)]
        task_base_domain = [('deleted', '=', False)]

        ticket_pending_domain = ticket_base_domain + [('state', '=', 'pending')]
        task_pending_domain = task_base_domain + [('state', '=', 'pending')]
        ticket_department_pending_domain = ticket_pending_domain + department_domain
        
        
        company = False
            
        """
        try:
            user = request.env.user
            if user.ui_selected_company_id and user.ui_selected_company_id > 0:
                company = request.env['res.company'].browse(user.ui_selected_company_id)
        except:
            company = False
        """
            
        if not company:
            company = request.env.company

        values = {
            'ticket_mine_count': ticket_model.search_count(ticket_pending_domain + [('assigned_to_user', '=', user_partner.id)]),
            'task_mine_count': task_model.search_count(task_pending_domain + [('assigned_to_user', '=', user_partner.id)]),
            'ticket_submit_url': self._action_url('ssw.tickets.templates', ticket_submit_action.id, tickets_menu.id),
            'procedure_start_url': self._action_url('ssw.proc.templates', procedure_start_action.id, procedures_menu.id),
            'tickets_url': self._action_url('ssw.tickets', tickets_action.id, tickets_menu.id),
            'tasks_url': self._action_url('ssw.proc.tasks', tasks_action.id, tasks_menu.id),
            'tickets': [
                self._serialize_ticket(ticket, tickets_action.id, tickets_menu.id)
                for ticket in ticket_model.search(ticket_department_pending_domain, limit=8)
            ],
            'tasks': [
                self._serialize_task(task, tasks_action.id, tasks_menu.id)
                for task in task_model.search(task_pending_domain, limit=8)
            ],
            'company': company,
        }
        return request.render("ss_workflow.dashboard", values)
