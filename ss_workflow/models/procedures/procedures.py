# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from datetime import date, datetime, timedelta
from markupsafe import Markup
from .tasks import ssw_task


class ssw_procedure(models.Model):
    _name = 'ssw.procedures'
    _description = 'Procedure'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = "create_date desc"

    name                     = fields.Char(string='Title', required=True)
    description              = fields.Text()
    
    state                    = fields.Selection([("closed", 'Canceled'), ("pending", 'Ongoing'), ("completed", 'Completed')], default='pending', required=True, tracking=True)
    tasks                    = fields.One2many('ssw.proc.tasks', 'procedure_id', string='Tasks', domain=[('state', '!=', 'draft')])
    tasks_read               = fields.One2many('ssw.proc.tasks.read', 'procedure_id', string='Tasks', domain="[('state', '!=', 'skipped')]" )

    template_id              = fields.Many2one('ssw.proc.templates', string='Procedure Template', required=True, tracking=True, domain=lambda self: self._get_template_domain())
    created_by               = fields.Many2one('res.partner', string='Created by', tracking=True)
    users_can_view           = fields.Many2many('res.partner', string='Users can view')
    
    resolve_max_duration     = fields.Integer(string='SLA Duration hours', default=168, tracking=True)
    resolve_deadline         = fields.Datetime(string='Resolve deadline', tracking=True)
    resolve_duration         = fields.Integer(string='Resolve duration', default=0, tracking=True)
    resolve_deadline_passed  = fields.Integer(string='SLA Passed', help="How many hours it passed the SLA", default=0, tracking=True)

    # optionals for linking
    optional_partner_id      = fields.Many2one('res.partner', string='Associated contact', tracking=True)
    optional_user_id         = fields.Many2one('res.users', string='Associated user', tracking=True)
    optional_ticket_id       = fields.Many2one('ssw.tickets', string='Parent ticket', tracking=True)
    optional_procedure_id    = fields.Many2one('ssw.procedures', string='Parent procedure', tracking=True)
    optional_task_id         = fields.Many2one('ssw.proc.tasks', string='Parent task', tracking=True)
    
    ssw_ticket_count         = fields.Integer(string="Tickets count", compute="_compute_ssw_tickets_count")
    ssw_ticket_ids           = fields.One2many('ssw.tickets', 'optional_procedure_id', string="Tickets")
    
    ssw_procedure_count      = fields.Integer(string="Procedures count", compute="_compute_ssw_procedures_count")
    ssw_procedure_ids        = fields.One2many('ssw.procedures', 'optional_procedure_id', string="Procedures")
    
    deleted                  = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    @api.depends('ssw_ticket_ids')
    def _compute_ssw_tickets_count(self):
        for proc in self:
            proc.ssw_ticket_count = self.env['ssw.tickets'].sudo().search_count([('optional_procedure_id', '=', proc.id), ('deleted', '=', False)])
        
    def action_get_tickets_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.tickets',
            'domain': [('optional_procedure_id', '=', self.id)],
            'context': {
                'default_optional_procedure_id': self.id,
                'create': True,
            }
        }
    
    @api.depends('ssw_procedure_ids')
    def _compute_ssw_procedures_count(self):
        for proc in self:
            proc.ssw_procedure_count = self.env['ssw.procedures'].sudo().search_count([('optional_procedure_id', '=', proc.id), ('deleted', '=', False)])
        
    def action_get_procedures_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Procedures',
            'view_mode': 'list,form',
            'res_model': 'ssw.procedures',
            'domain': [('optional_procedure_id', '=', self.id)],
            'context': {
                'default_optional_procedure_id': self.id,
                'create': True,
            }
        }
        
        
    def _get_template_domain(self):
        current_user   = self.env.user
        partner_id = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        groups_can_see = partner_id.groups_can_see.ids  # Get the IDs of the groups
        return [('enabled', '=', True), ('deleted', '=', False), ('default_group', 'in', groups_can_see), ('departments_can_create', 'in', [partner_id.default_department.id])]

    def create(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            current_user   = self.env.user
            if current_user:
                partner = current_user.partner_id
                workflow_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id), ('deleted', '=', False)], limit=1)
                groups_can_see = workflow_user.groups_can_see.ids  # Get the IDs of the groups
                template_payload = [('id', '=', payload['template_id']), ('enabled', '=', True), ('deleted', '=', False), ('default_group', 'in', groups_can_see), ('departments_can_create', 'in', [workflow_user.default_department.id])]
            else:
                partner = self.env['res.partner'].sudo().browse([payload['created_by']])
                workflow_user = self.env['ssw.users'].sudo().search([('partner_id', '=', partner.id), ('deleted', '=', False)], limit=1)
                template_payload = [('id', '=', payload['template_id']), ('enabled', '=', True), ('deleted', '=', False), ('default_group', 'in', workflow_user.groups_can_see.ids)]

            template = self.env['ssw.proc.templates'].sudo().search(template_payload, limit=1)
            payload['created_by']        = partner.id
            payload['resolve_deadline']  = datetime.now() + timedelta(hours=template.resolve_max_duration)
            payload['users_can_view']    = [partner.id]
            payload['resolve_max_duration'] = template.resolve_max_duration
            
            procedure_created = super().create(payload)
            
            first_task = False
            # we add the inputs
            tasks = self.env['ssw.proc.templates.tasks'].sudo().search([('template_id', '=', template.id), ('deleted', '=', False), ('enabled', '=', True)], order="task_sequance asc")
            if len(tasks) == 0:
                raise exceptions.ValidationError("Procedure has no tasks")
            
            for task in tasks:
                payload = {
                    "created_by": partner.id,
                    "procedure_id": procedure_created.id,
                    "task_id": task.id,
                    "name": task.name,
                    "description": task.description,
                    "default_group": task.default_group,
                    "has_procedures": task.has_procedures,
                    "ignore_state": task.ignore_state,
                    "has_path_choice": task.has_path_choice,
                    "path_choice_question": task.path_choice_question,
                    "unlock_datetime": datetime.now(),
                    "resolve_max_duration": task.resolve_max_duration
                }
                
                if not first_task:
                    payload['users_can_view'] = [(4, partner.id)]
                    
                created_task = self.env['ssw.proc.tasks'].sudo().create(payload)
                for sub_procedure in task.sub_procedures:
                    self.env['ssw.task.procedure.line'].sudo().create({
                        "task_id": created_task.id,
                        "template_id": sub_procedure.id
                    })
                
                
                
                self.env['ssw.proc.tasks.read'].sudo().create({
                    "name": task.name,
                    "description": task.description,
                    "task_sequance": task.task_sequance,
                    "procedure_id": procedure_created.id,
                    "task_id": created_task.id
                })
                
                if template.creator_see_tasks:
                    created_task.write({
                        'users_can_view': [(4, partner.id)]
                    })
                
                if not first_task or created_task.task_sequance == first_task.task_sequance:
                    first_task = created_task
                    created_task.state = "pending"
              
            tasks = self.env['ssw.proc.tasks'].sudo().search([('procedure_id', '=', procedure_created.id)])
            for task in tasks:
                next_tasks_ids = []
                template = task.task_id
                for next_task in template.next_task_ids:
                    next_task = self.env['ssw.proc.tasks'].sudo().search([('procedure_id', '=', procedure_created.id), ('task_id', '=', next_task.id)], limit=1)
                    if next_task:
                        next_tasks_ids.append(next_task.id)
                if len(next_tasks_ids) > 0:
                    task.next_task_ids = [(6, 0, next_tasks_ids)]
                    
                for path_choice in template.path_choices:
                    target_choice = self.env['ssw.proc.tasks'].sudo().search([('task_id', '=', path_choice.target_task_id.id), ('procedure_id', '=', task.procedure_id.id)], limit=1)
                    created_choice = self.env['ssw.proc.taskpaths'].sudo().create({
                        "name": path_choice.name,
                        "task_id": task.id,
                        "target_task_id": target_choice.id
                    })
                    task.path_choices = [(4, created_choice.id)]
                    
                task.finished_creation = True
                    
            managers = self.env['ssw.managers'].sudo().search([
                ('default_department', 'in', [workflow_user.default_department.id]), ('deleted', '=', False)
            ])
            
            for manager in managers:
                procedure_created.write({
                    'users_can_view': [(4, manager.user_id.partner_id.id)]
                })
                
            procedure_created.write({
                'users_can_view': [(4, partner.id)]
            })
            
            self.handle_inputs_viewed_in_other_tasks(procedure_created=procedure_created)
            return procedure_created
        
    def write(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            
            for not_modifiable in ['resolve_deadline', 'resolve_max_duration', 'resolve_deadline_passed', 'created_by']:
                if not_modifiable in payload:
                    raise exceptions.ValidationError("field cannot be modified.")
                
            if self.state != 'pending':
                raise exceptions.ValidationError("Procedure is already finished. " + self.state)
                
            if 'state' in payload:
                payload['resolve_duration'] = 0
                tasks = self.env['ssw.proc.tasks'].sudo().search([('procedure_id', '=', self.id), ('ignore_state', '=', False)])
                
                if payload['state'] == 'completed':
                    for task in tasks:
                        if task.state == 'pending':
                            raise exceptions.ValidationError("Procedure has pending tasks")
                        
                        self.env['ssw.proc.taskpaths'].sudo().search([('task_id', '=', task.id)]).unlink()
                    skipped_tasks = self.env['ssw.proc.tasks'].sudo().search([("procedure_id", "=", self.id), ("state", "in", ["skipped", "draft", "rejected"])])
                    for skipped_task in skipped_tasks:
                        try:
                            with self.env.cr.savepoint():
                                self.env['ssw.proc.tasks.read'].sudo().search(
                                    [('task_id', '=', skipped_task.id)]
                                ).unlink()
                                inputs = self.env['ssw.proc.tasks.inputs'].sudo().search(
                                    [('task_id', '=', skipped_task.id)]
                                )
                                for input in inputs:
                                    self.env['ssw.proc.tasks.inputs.subs'].sudo().search(
                                        [('input_id', '=', input.id)]
                                    ).unlink()
                                    input.unlink()
                                super(ssw_task, skipped_task).unlink()
                        except Exception as e:
                            pass
                        
                    payload['resolve_duration'] = (datetime.now() - self.create_date).total_seconds() / 3600
                    payload['resolve_deadline_passed'] = payload['resolve_duration'] - self.resolve_max_duration
                    if payload['resolve_deadline_passed'] < 0:
                        payload['resolve_deadline_passed'] = 0
                        
                    if self.created_by:
                        self.notify_user(self.created_by.id, "Procedures is completed")
                elif payload['state'] == 'closed':
                    if self.created_by:
                        self.notify_user(self.created_by.id, "Procedures is closed")
                    
                    for task in tasks:
                        task._close()
                
            result = super().write(payload)
            
            if 'users_can_view' in payload and (payload["users_can_view"] == [[3, self.env.user.partner_id.id]] or len(self.users_can_view) == 0):
                raise exceptions.ValidationError("You can not remove yourself from the list")
            return result
        
    def complete_procedure(self):
        allowed_groups = ['ss_workflow.group_admin', 'ss_workflow.group_manager', 'ss_workflow.group_submanager']
        for group in allowed_groups:
            if self.env.user.has_group(group):
                super().write({ "state": "completed" })
                for task in self.tasks:
                    if task.state == 'pending':
                        super(ssw_task, task).write({ "state": "completed" })
                        task._update_read_task()
                return True
        raise exceptions.ValidationError("You do not have permission to complete a procedures")
        
    def update_users_view_only(self, users):
        return super().write({ "users_can_view": users })
        
    def handle_inputs_viewed_in_other_tasks(self, procedure_created):
        # we now set the inputs showing in other inputs
        ids = []
        created_tasks = self.env['ssw.proc.tasks'].sudo().search([('procedure_id', '=', procedure_created.id)])
        for created_task in created_tasks:
            inputs_with_other_show = self.env['ssw.proc.tasks.inputs'].sudo().search([('task_id', '=', created_task.id)])
            for input_with_other_show in inputs_with_other_show:
                has_show_in_other_task_templates = input_with_other_show.input_template_id.show_in_other_tasks
                if has_show_in_other_task_templates:
                    for tasks_template in has_show_in_other_task_templates:
                        
                        other_tasks_to_add = self.env['ssw.proc.tasks'].sudo().search([
                            (
                                'procedure_id', '=', procedure_created.id,
                            ),
                            (
                                'task_id', '=', tasks_template.id
                            )
                        ])
                        for other_task in other_tasks_to_add:
                            input_with_other_show.show_in_other_tasks = [(4, other_task.id)]
                            
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True

    def notify_user(self, partner_id, message):
        self.ensure_one()
        
        bot_partner = self.env.ref('base.partner_root')
        partner = self.env['res.partner'].browse(partner_id)
        
        
        recipient_user = self.env['res.users'].sudo().search([('partner_id', '=', partner.id)], limit=1)
        if not recipient_user:
            return True
        
        channel = self.env['discuss.channel'].with_user(recipient_user.id).sudo().channel_get([bot_partner.id])

        # Some versions return a dict; normalize to a recordset
        if isinstance(channel, dict):
            channel = self.env['discuss.channel'].sudo().browse(channel.get('id'))
            
        record_url = f"/web#id={self.id}&model={self._name}&view_type=form"
            
        body = Markup(f"""
            <p>{message}</p>
            <br>
            <p>
                <a href="{record_url}" class="o_channel_redirect">
                    View
                </a>
            </p>
        """)
            
        # Send chat message
        channel.message_post(
            body=body,
            author_id = bot_partner.id,
            message_type = "comment",
            subtype_xmlid = "mail.mt_comment",
        )
        
