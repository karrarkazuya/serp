# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from datetime import date, datetime, timedelta
from markupsafe import Markup


class ssw_task(models.Model):
    _name = 'ssw.proc.tasks'
    _description = 'Task'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = "resolve_deadline asc"
    
    @api.model
    def _search(self, domain, offset=0, limit=None, order=None):
        if self._context.get('task_return_wizard'):
            self = self.sudo()
        return super()._search(domain, offset=offset, limit=limit, order=order)

    name                     = fields.Char(string='Title', required=True)
    description              = fields.Text()
    context                  = fields.Text('Context', readonly=True)
    
    state                    = fields.Selection([("rejected", 'Rejected'), ("draft", 'Draft'), ("pending", 'Pending'), ("skipped", 'Skipped'), ("completed", 'Completed')], default='draft', required=True, tracking=True)
    
    task_sequance            = fields.Integer(string='Task Sequance', tracking=True)
    procedure_id             = fields.Many2one('ssw.procedures', string='Procedure', tracking=True, domain=lambda self: self._get_template_domain())
    task_id                  = fields.Many2one('ssw.proc.templates.tasks', string='Task Template', required=True, tracking=True, domain=lambda self: self._get_template_domain())
    previous_task_id         = fields.Many2one('ssw.proc.tasks', string='Previous task', tracking=True)
    next_task_ids            = fields.Many2many(comodel_name='ssw.proc.tasks', relation='ssw_proc_task_next_rel', column1='source_task_id', column2='next_task_id', string='Next Tasks', tracking=True)
    task_read_id             = fields.Many2one('ssw.proc.tasks.read', string='Read')
    
    assigned_to_user         = fields.Many2one('res.partner', string='Assigned User', tracking=True)
    assigned_to_dep          = fields.Many2one('ssw.departments', string='Assigned Department', tracking=True, domain=lambda self: self._get_department_domain())
    users_can_view           = fields.Many2many('res.partner', string='Users can view')
    inputs                   = fields.One2many('ssw.proc.tasks.inputs', 'task_id', string='Inputs', tracking=True, domain=lambda self: self._get_inputs_domain())
    
    resolve_max_duration     = fields.Integer(string='SLA Duration hours', default=168, tracking=True)
    resolve_deadline         = fields.Datetime(string='Resolve deadline', tracking=True)
    unlock_datetime          = fields.Datetime(string='Unlock Date', tracking=True)
    resolve_duration         = fields.Integer(string='Resolve duration', default=0, tracking=True)
    resolve_deadline_passed  = fields.Integer(string='SLA Passed', help="How many hours it passed the SLA", default=0, tracking=True)
    
    is_approve_only          = fields.Boolean(string='Is Approve task Only', default=False, tracking=True)
    other_tasks_inputs       = fields.Many2many('ssw.proc.tasks.inputs', string='Other tasks inputs', compute='_set_other_inputs_id')
    created_by               = fields.Many2one('res.partner', string='Created by', tracking=True)
    default_group            = fields.Many2many('ssw.groups', string='Allowed group', help="If set, only users of those groups can view", tracking=True)
    
    assigned_to_user_domain  = fields.Binary("assigned_to_user_domain", default=[], compute='_compute_assigned_to_user_domain', save=False)
    
    # optionals for linking
    optional_partner_id      = fields.Many2one('res.partner', string='Associated contact', tracking=True)
    optional_user_id         = fields.Many2one('res.users', string='Associated user', tracking=True)
    optional_procedure_id    = fields.Many2one('ssw.procedures', string='Parent Procedure', tracking=True)
    optional_ticket_id       = fields.Many2one('ssw.tickets', string='Parent ticket', tracking=True)
    
    ssw_ticket_count          = fields.Integer(string="Tickets count", compute="_compute_ssw_tickets_count")
    ssw_ticket_ids            = fields.One2many('ssw.tickets', 'optional_task_id', string="Tickets")
    
    ssw_procedure_count       = fields.Integer(string="Procedures count", compute="_compute_ssw_procedures_count")
    ssw_procedure_ids         = fields.One2many('ssw.procedures', 'optional_task_id', string="Procedures")
    
    has_procedures           = fields.Boolean(string='Has procedures', default=False, tracking=True, readonly=True)
    sub_procedure_lines      = fields.One2many('ssw.task.procedure.line', 'task_id', string='Required Procedures', readonly=True)

    has_path_choice          = fields.Boolean(string='Has path choice', default=False, tracking=True, readonly=True)
    path_choice_question     = fields.Char(string='Question', help='this task can not proceed without answering this question', readonly=True)
    path_choices             = fields.One2many('ssw.proc.taskpaths', 'task_id', string='Path Choices', help="Set path choices", tracking=True)
    path_chosen              = fields.Many2one('ssw.proc.taskpaths', string='Answer', domain="[('task_id', '=', id)]", tracking=True)
    
    ignore_state             = fields.Boolean(string='Ignore state', default=False, tracking=True, readonly=True)
    
    return_reason            = fields.Text(string='Return Reason', tracking=True)
    
    durations                = fields.One2many('ssw.tasks.durations', 'ticket_id', string='Resolve Durations')

    finished_creation        = fields.Boolean(string='Finished Creation', default=False)
    deleted                  = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    @api.depends('ssw_ticket_ids')
    def _compute_ssw_tickets_count(self):
        for item in self:
            item.ssw_ticket_count = self.env['ssw.tickets'].sudo().search_count([('optional_task_id', '=', item.id), ('deleted', '=', False)])
    
    @api.depends('ssw_procedure_ids')
    def _compute_ssw_procedures_count(self):
        for item in self:
            item.ssw_procedure_count = self.env['ssw.procedures'].sudo().search_count([('optional_task_id', '=', item.id), ('deleted', '=', False)])

    def action_get_tickets_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.tickets',
            'domain': [('optional_task_id', '=', self.id)],
            'context': {
                'default_optional_task_id': self.id,
                'create': True,
            }
        }
        
    def action_get_procedures_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.procedures',
            'domain': [('optional_task_id', '=', self.id)],
            'context': {
                'default_optional_task_id': self.id,
                'create': True,
            }
        }
            
    def _set_other_inputs_id(self):
        for record in self:
            record.other_tasks_inputs = self.env['ssw.proc.tasks.inputs'].sudo().search([('show_in_other_tasks', '=', record.id)])
    
    def _get_template_domain(self):
        current_user   = self.env.user
        partner_id     = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        groups_can_see = partner_id.groups_can_see.ids  # Get the IDs of the groups
        return [('enabled', '=', True), ('deleted', '=', False), ('default_group', 'in', groups_can_see), ('departments_can_create', 'in', [partner_id.default_department.id])]

    def _get_department_domain(self):
        department_ids = self.env['ssw.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1).departments_can_assign.ids
        return [('id', 'in', department_ids)]


    def _get_inputs_domain(self):
        return [('deleted', '=', False)]


    @api.depends('assigned_to_dep')
    def _compute_assigned_to_user_domain(self):
        partner_ids = []
        for record in self:
            department_id = record.assigned_to_dep.id
            users = self.env['ssw.users'].sudo().search([('default_department', '=', department_id)])
            for user in users:
                partner_ids.append(user.partner_id.id)
            if len(partner_ids) == 0:
                record.assigned_to_user_domain = [("id", "=", -10)]
            else:
                record.assigned_to_user_domain = [("id", "in", partner_ids)]
            
        
    def create(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            current_user   = self.env.user
            if current_user:
                partner = current_user.partner_id
                template_payload = [('id', '=', payload['task_id']), ('enabled', '=', True), ('deleted', '=', False)]
            else:
                partner = self.env['res.partner'].browse([payload['created_by']])
                template_payload = [('id', '=', payload['task_id']), ('enabled', '=', True), ('deleted', '=', False)]
                
            template = self.env['ssw.proc.templates.tasks'].sudo().search(template_payload, limit=1)
        
            payload['assigned_to_dep']   = template.default_department.id
            payload['resolve_deadline']  = datetime.now() + timedelta(hours=template.resolve_max_duration)
            payload['is_approve_only']   = template.is_approve_only
            payload['task_sequance']     = template.task_sequance
            
            if 'procedure_id' not in payload:
                task_template = self.env['ssw.proc.templates.tasks'].sudo().search([
                    ('id', '=', payload['task_id']),
                    ('deleted', '=', False),
                    ('enabled', '=', True)
                ], limit=1)
                payload['state']                = 'pending'
                payload['unlock_datetime']      = datetime.now()
                payload['resolve_max_duration'] = task_template.resolve_max_duration
                payload['has_procedures']       = task_template.has_procedures
                payload['default_group']        = task_template.default_group
                payload['created_by']           = partner.id
                
                task_created = super(ssw_task, self.sudo()).create(payload)
            else:
                one_minute_ago = fields.Datetime.now() - timedelta(minutes=1)
                procedure = self.env['ssw.procedures'].sudo().search([
                    ('id', '=', payload['procedure_id']),
                    ('state', '=', 'pending'),
                    ('deleted', '=', False),
                    ('create_uid', '=', self.env.uid),
                    ('create_date', '>=', one_minute_ago),
                ], limit=1)
                if not procedure:
                    raise exceptions.ValidationError("Unauthorized task creation")
                task_created = super().create(payload)
            
            # we add the inputs
            for input in template.sudo().inputs:
                new_input = self.env['ssw.proc.tasks.inputs'].sudo().create({
                    "task_id": task_created.id,
                    "input_template_id": input.id,
                    "name": input.name,
                    "is_required": input.is_required,
                    "type": input.type,
                    "reference_model_name": input.reference_model_name,
                    "allow_multiple_model_values": input.allow_multiple_model_values,
                })

                if len(input.inputs_sub) > 0:
                    for sub in input.sudo().inputs_sub:
                        self.env['ssw.proc.tasks.inputs.subs'].sudo().create({
                            "input_id": new_input.id,
                            "name": sub.name
                        })
                    
                task_created.write({
                    'inputs': [(4, new_input.id)]
                })
                
            procedure = False
            if task_created.procedure_id:
                procedure = task_created.procedure_id
            # we add the users who can see
            users_with_dep = self.env['ssw.users'].search([("default_department", "=", template.default_department.id), ('deleted', '=', False)])
            for memeber in users_with_dep:
                partner_id = memeber.partner_id
                allowed_group_ids = task_created.default_group.ids
                add_to_task = True
                if len(allowed_group_ids) > 0:
                    groups_can_see = memeber.groups_can_see.ids
                    has_common = any(item in groups_can_see for item in allowed_group_ids)
                    add_to_task = has_common
                    
                if add_to_task:
                    if partner_id.id:
                        task_created.write({
                            'users_can_view': [(4, partner_id.id)]
                        })
                        if procedure:
                            procedure.write({
                                'users_can_view': [(4, partner_id.id)]
                            })
                    
            managers = self.env['ssw.managers'].sudo().search([
                ('default_department', 'in', [template.default_department.id]), ('deleted', '=', False)
            ])
            
            for manager in managers:
                task_created.write({
                    'users_can_view': [(4, manager.user_id.partner_id.id)]
                })
            
            if procedure:
                template = procedure.template_id
                for manager in managers:
                    procedure.write({
                        'users_can_view': [(4, manager.user_id.partner_id.id)]
                    })
            return task_created
        
    def write(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            
            update_procedure = False
            for not_modifiable in ['procedure_id', 'task_id', 'resolve_deadline', 'resolve_max_duration', 'resolve_deadline_passed', 'created_by']:
                if not_modifiable in payload:
                    raise exceptions.ValidationError("field cannot be modified.")
                
            next_pending_tasks = self._next_pending_tasks()
            previous_task = self._previous_task()
            
            if self.procedure_id and self.procedure_id.state != 'pending':
                raise exceptions.ValidationError("Unable to modify task, procedure is already finished. " + self.procedure_id.state)
            
            if (self.state in ['skipped', 'draft'] and self.finished_creation) or ('finished_creation' in payload and self.finished_creation):
                raise exceptions.ValidationError("Unable to modify task, task is not on going")
            
            
            if 'state' in payload:
                
                # we check if there is pending previously
                if previous_task and previous_task.state == "pending" and not previous_task._has_parallel_completed():
                    raise exceptions.ValidationError("Previous task is still pending.")
                
                payload['resolve_duration'] = 0
                
                if payload['state'] == 'rejected':
                    payload['assigned_to_user'] = False
                    
                if payload['state'] == 'completed':  
                    if not self.assigned_to_user:
                        raise exceptions.ValidationError("Please assign first.")              
                    
                    for input in self.inputs:
                        if input.is_required and not input.result_value:
                            raise exceptions.ValidationError("There is a required field left, please fill " + input.name)
                        
                    for item in self.sub_procedure_lines:
                        if item.state != "completed":
                            raise exceptions.ValidationError("The following procedure needs to be completed first (" + item.name + ")")
                        
                    payload['resolve_duration'] = (datetime.now() - self.unlock_datetime).total_seconds() / 3600
                    payload['resolve_deadline_passed'] = payload['resolve_duration'] - self.resolve_max_duration
                    if payload['resolve_deadline_passed'] < 0:
                        payload['resolve_deadline_passed'] = 0
                        
                    update_procedure = True
                
                if payload['state'] == 'pending':
                    for next_task in next_pending_tasks:
                        if next_task.state != 'draft' and  next_task.assigned_to_user:
                            raise exceptions.ValidationError("Can not do because task (#" + str(next_task.id) + ") has been already assigned")
                
            result = super().write(payload)
            
            if 'assigned_to_user' in payload or self.state == "completed":      
                last_user = self.assigned_to_user
                if last_user:
                    previous_resolve_duration = (datetime.now() - self.write_date).total_seconds() / 3600
                    self.env['ssw.tasks.durations'].sudo().create({
                        "ticket_id": self.id,
                        "user_id": last_user.id,
                        "duration": previous_resolve_duration
                    })
            
            if self.has_path_choice and not self.path_chosen and 'state' in payload and payload['state'] == 'completed':
                raise exceptions.ValidationError("You must answer the question below first")
            
            if not self.ignore_state and update_procedure:
                self._unlock_next()
                if  not self._can_not_complete():
                    if self.procedure_id:
                        self.procedure_id.state = 'completed'
            
            if 'state' in payload and payload['state'] == 'rejected':
                if not self.ignore_state:
                    if not self.previous_task_id:
                        if self.procedure_id:
                            self.procedure_id.state = 'closed'
                    else:
                        if previous_task:
                            previous_task.write({
                                "state": "pending",
                                "return_reason": payload.get('return_reason', False),
                            })
                            previous_task.notify_returned_assigned_department(payload.get('return_reason', False))
            
            if 'assigned_to_dep' in payload:
                # we add the users who can see
                users_with_dep = self.env['ssw.users'].search([("default_department", "=", self.assigned_to_dep.id), ('deleted', '=', False)])
                for memeber in users_with_dep:
                    self.write({
                        'users_can_view': [(4, memeber.partner_id.id)]
                    })
                    self.notify_user(partner_id=memeber.partner_id.id, message="This task was assigned to your department (" + str(self.name) + ")")
                    
                managers = self.env['ssw.managers'].sudo().search([
                    ('default_department', 'in', [self.assigned_to_dep.id]), ('deleted', '=', False)
                ])
                for manager in managers:
                    self.write({
                        'users_can_view': [(4, manager.user_id.partner_id.id)]
                    })
                
                if self.procedure_id:
                    procedure = self.procedure_id
                    for manager in managers:
                        procedure.write({
                            'users_can_view': [(4, manager.user_id.partner_id.id)]
                        })
                
                        
            if 'assigned_to_user' in payload and payload['assigned_to_user']:
                self.write({
                    'users_can_view': [(4, payload['assigned_to_user'])]
                })
                if self.procedure_id:
                    self.procedure_id.write({
                        'users_can_view': [(4, payload['assigned_to_user'])]
                    })
                
                partner = self.env['res.partner'].sudo().search([
                    (
                        'id', '=', payload['assigned_to_user']
                    )
                ], limit=1)
                self.notify_user(partner_id=partner.id, message="This task was assigned to you (" + str(self.name) + ")")
                
            
            if 'users_can_view' in payload and (payload["users_can_view"] == [[3, self.env.user.partner_id.id]] or len(self.users_can_view) == 0):
                raise exceptions.ValidationError("You can not remove yourself from the list")
            
            if 'state' in payload and payload['state'] == 'pending':
                # we draft coming pending tasks
                if not self.ignore_state:
                    for pending_future_task in self._next_tasks():
                        if pending_future_task.state in ['pending']:
                            pending_future_task._draft()
           
            self._update_read_task()
            if 'users_can_view' in payload:
                for input in self.inputs:
                    input._handle_extra_modules()
            return result
        
    def update_users_view_only(self, users):
        return super().write({ "users_can_view": users })
    
    
    def complete_task(self):
        for record in self:
            record.write({'state': 'completed'})
            
    def close_task(self):
        self.ensure_one()
        return {
            'name': 'Return Task',
            'type': 'ir.actions.act_window',
            'res_model': 'ssw.proc.task.return.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {'default_task_id': self.id},
        }

    def _do_close_task(self, reason):
        self.ensure_one()
        self.write({'state': 'rejected', 'return_reason': reason})

    def return_to_task(self):
        self.ensure_one()
        return {
            'name': 'Return to Task',
            'type': 'ir.actions.act_window',
            'res_model': 'ssw.proc.task.return.to.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {'default_task_id': self.id},
        }
        
    def _get_children(self, task, stop_at_id=None, _seen=None):
        """Return ids of `task` and all its descendants via next_task_ids.

        If stop_at_id is given, traversal halts when that task is reached
        (the stop task itself is included in the result).
        """
        if _seen is None:
            _seen = set()

        if task.id in _seen:
            return []
        _seen.add(task.id)

        ids = [task.id]

        if task.id == stop_at_id:
            return ids

        for next_task in task.next_task_ids:
            ids.extend(self._get_children(next_task, stop_at_id, _seen))

        return ids

    def _do_return_to_task(self, target_task, reason):
        self.ensure_one()
        
        if self.procedure_id.state != 'pending':
            raise exceptions.ValidationError("Unable to modify task, procedure is already finished. " + self.procedure_id.state)

        # Reject current task (bypass write logic to avoid triggering normal return chain)
        super(ssw_task, self).write({'state': 'rejected', 'return_reason': reason})
        self._update_read_task()
        tasks_between = self._get_children(target_task, self.id)
        for t in tasks_between:
            if t != target_task:
                t = self.env['ssw.proc.tasks'].sudo().browse([t])
                if t.state != 'skipped':
                    super(ssw_task, t).write({'state': 'draft', 'return_reason': False})
                    t._update_read_task()

        # Re-open the target task with the reason
        super(ssw_task, target_task).write({
            'state': 'pending',
            'return_reason': reason,
            'unlock_datetime': datetime.now(),
        })
        target_task._update_read_task()
        target_task.notify_returned_assigned_department(reason)
            
    def pending_task(self):
        for record in self:
            record.write({'state': 'pending'})
        
    def _update_read_task(self):
        task_read = self.env['ssw.proc.tasks.read'].sudo().search([('task_id', '=', self.id)], limit=1)
        if task_read:
            task_read.state = self.state
            
            if task_read.state != 'draft' and not task_read.available_at:
                task_read.available_at = datetime.now()
                
            
            if self.assigned_to_dep:
                task_read.assigned_to_dep = self.assigned_to_dep.id
            if self.assigned_to_user:
                task_read.assigned_to_user = self.assigned_to_user.id
                
            task_read.resolve_deadline = self.resolve_deadline
            task_read.resolve_duration = self.resolve_duration
            task_read.resolve_deadline_passed = self.resolve_deadline_passed
            
    def _unlock_next(self):
        current_task = self.env['ssw.proc.tasks'].sudo().browse([self.id])
            
        tasks_to_unlock = []
        other_task_ids = []
        if current_task.has_path_choice and not current_task.path_chosen:
            raise exceptions.ValidationError("You must answer the question first")
        if current_task.has_path_choice and current_task.path_chosen.target_task_id:
            tasks_to_unlock.append(current_task.path_chosen.target_task_id)
            for task in current_task.path_choices:
                if task.target_task_id.id != current_task.path_chosen.target_task_id:
                    other_task_ids.append(task.target_task_id.id)
            
        for task in current_task.next_task_ids:
            if task.id not in other_task_ids:
                tasks_to_unlock.append(task)
                
        for task in tasks_to_unlock:
            if task.state != 'completed':
                payload = {
                    'state': 'pending',
                    'previous_task_id': current_task.id
                }
                if task.state == 'draft':
                    payload['unlock_datetime'] = datetime.now()
                    
                super(ssw_task, task).write(payload)
                task._update_read_task()
                task.notify_assigned_department()
        
    def _unlock(self, previous_id):
        
        self.ensure_one()
        
        self.notify_assigned_department()
        
        payload = {
            "unlock_datetime": datetime.now(),
            "previous_task_id": previous_id,
            "state": "pending"
        }
        
        super().write(payload)
        self._update_read_task()
        
    def _close(self):
        self.ensure_one()
        if self.state == "pending":
            super().write({ "state": "rejected" })
            self._update_read_task()
            
    def _draft(self):
        self.ensure_one()
        if self.state == "pending":
            super().write({ "state": "draft" })
            self._update_read_task()
            
            
    def _can_not_complete(self):
        can_complete = self.env['ssw.proc.tasks'].sudo().search([
            (
                'id', '!=', self.id
            ),
            (
                'procedure_id', '=', self.procedure_id.id
            ),
            (
                'state', '=', 'pending'
            ),
            (
                'ignore_state', '=', False
            )
        ], limit=1)
        
        if can_complete and can_complete.id:
            return True
        return False
    
    def _has_parallel_completed(self):
        task = self.env['ssw.proc.tasks'].sudo().browse([self.id])
        previous_task = task.previous_task_id
        for task in previous_task.next_task_ids:
            if task.state == "completed":
                return True
        return False
            
    def _previous_task(self):
        task = self.env['ssw.proc.tasks'].sudo().browse([self.id])
        return task.previous_task_id
    
    def _next_tasks(self):
        tasks = []
        task = self.env['ssw.proc.tasks'].sudo().browse([self.id])
        for task in task.next_task_ids:
            if task.state != "skipped":
                tasks.append(task)
        return tasks
        
    def _next_pending_tasks(self):
        result = []
        for task in self._next_tasks():
            if task.state in ['draft', 'rejected']:
                result.append(task)
        return result
            
    def notify_assigned_department(self):
        if self.assigned_to_dep:
            users = self.env['ssw.users'].sudo().search([('default_department', '=', self.assigned_to_dep.id)])
            for user in users:
                if user.partner_id:
                    self.notify_user(user.partner_id.id, "A new task was assgined to your department")
                    
    def notify_returned_assigned_department(self, reason):
        if self.assigned_to_dep:
            users = self.env['ssw.users'].sudo().search([('default_department', '=', self.assigned_to_dep.id)])
            for user in users:
                if user.partner_id and user.partner_id.id:
                    message = "A new task was returned to your department"
                    if reason:
                        message = message + " (" + str(reason) + ")"
                        
                    self.notify_user(user.partner_id.id, message)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    def claim_task(self):
        for record in self:
            if record.state != "pending":
                raise exceptions.ValidationError("Task must be set to pending first")
            record.write({'assigned_to_user': self.env.user.partner_id.id, "state": "pending"})
            
    def un_claim_task(self):
        for record in self:
            if record.state != "pending":
                raise exceptions.ValidationError("Task must be set to pending first")            
            record.write({'assigned_to_user': False})
            
    def view_task(self):
        for record in self:
            return {
                'name': 'Task Form',
                'type': 'ir.actions.act_window',
                'res_model': 'ssw.proc.tasks',  # Target model
                'view_mode': 'form',
                'res_id': record.id,  # ID of the record to open
                'target': 'current',  # Open in current window (or use 'new' for popup)
            }
            
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
    
    def updateContext(self):
        self.ensure_one()
        context = str(self.id) + ' ' + str(self.name) + ' ' + str(self.description)
        
        for input in self.inputs:
            context = context + ' ' + str(input.result_value)
            
        super().write({
            'context': context
        })
        

class ssw_tickets_deurations(models.Model):
    _name = 'ssw.tasks.durations'
    _description = 'Task Durations'

    ticket_id                     = fields.Many2one('ssw.proc.tasks', string='Task', required=True)
    user_id                       = fields.Many2one('res.partner', string='User')
    duration                      = fields.Float(string='Resolve duration (hours)', required=True)

    def write(self, vals):
        raise exceptions.ValidationError("Not modifiable")
        return False
    
    def unlink(self):
        raise exceptions.ValidationError("Not modifiable")
        return super().unlink()
