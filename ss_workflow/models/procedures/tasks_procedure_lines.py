# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from odoo.exceptions import ValidationError

class TaskProcedureLine(models.Model):
    _name = 'ssw.task.procedure.line'
    
    task_id = fields.Many2one('ssw.proc.tasks', required=True, ondelete='cascade')
    template_id = fields.Many2one('ssw.proc.templates', required=True)
    name = fields.Char(related='template_id.name')
    procedure_id = fields.Many2one('ssw.procedures')  # filled when "Start" is clicked
    state = fields.Selection([("not_started", 'Not started'), ("pending", 'Ongoing'), ("completed", 'Completed')], compute='_compute_state', store=True)
    
    @api.depends('procedure_id.state')
    def _compute_state(self):
        for line in self:
            if not line.procedure_id:
                line.state = 'not_started'
            else:
                if line.procedure_id.state == "closed":
                    line.state = 'not_started'
                    self.env['ssw.task.procedure.line'].sudo().browse([self.id]).write({
                        "procedure_id": False
                    })
                else:
                    line.state = line.procedure_id.state  # or map it
                
                
    def action_start_from_task(self):
        self.ensure_one()
        template_id = self.template_id
        
        task = self.env['ssw.proc.tasks'].sudo().search([('id', '=', self.task_id.id), ('users_can_view', 'in', [self.env.user.partner_id.id])])
        if not task:
            raise exceptions.ValidationError("You are not allowed to start this procedures.")
        
        procedure_exists = self.env['ssw.procedures'].sudo().search([('template_id', '=', template_id.id), ('optional_task_id', '=', task.id), ('state', 'in', ['pending', 'completed'])], limit=1)
        if procedure_exists and procedure_exists.id:
            raise exceptions.ValidationError("Procedure already exists, can not start new procedure")
        
        procedure = self.env['ssw.procedures'].sudo().create({
            "template_id": template_id.id,
            "optional_task_id": task.id,
            "name": template_id.name,
            "description": template_id.description,
        })
        
        self.env['ssw.task.procedure.line'].sudo().browse([self.id]).write({
            "procedure_id": procedure.id
        })
        
        tasks = procedure.tasks
        if tasks and len(tasks) > 0:
            tasks[0].assigned_to_user = self.env.user.partner_id.id
            return {
                'name': 'Task Form',
                'type': 'ir.actions.act_window',
                'res_model': 'ssw.proc.tasks',
                'view_mode': 'form',
                'res_id': tasks[0].id,
                'target': 'current',
            }
        return {
            'name': 'Procedure Form',
            'type': 'ir.actions.act_window',
            'res_model': 'ssw.procedures',
            'view_mode': 'form',
            'res_id': procedure.id,
            'target': 'current',
        }